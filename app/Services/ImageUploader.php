<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Upload seguro de imagens:
 *  - confere erro de upload, is_uploaded_file e tamanho máximo
 *  - detecta o tipo REAL pelo conteúdo (finfo + getimagesize), ignora nome/extensão enviados
 *  - limita resolução (proteção contra "decompression bomb")
 *  - RECODIFICA a imagem com GD: remove metadados (EXIF/GPS) e qualquer payload embutido
 *  - grava com nome aleatório e extensão fixa, numa pasta onde PHP não executa
 */
final class ImageUploader
{
    private const ALLOWED = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png' => IMAGETYPE_PNG,
        'image/webp' => IMAGETYPE_WEBP,
        'image/gif' => IMAGETYPE_GIF,
    ];
    private const MAX_PIXELS = 50_000_000; // ~50 MP

    public function __construct(
        private readonly string $uploadRoot,
        private readonly int $maxBytes,
    ) {
    }

    /**
     * @param array $file item de $_FILES
     * @param string $folder subpasta (photos, avatars, events, instagram)
     * @param int $maxSide maior lado da imagem final
     * @param int|null $thumbSide se informado, gera miniatura em {folder}/thumbs
     * @return string nome do arquivo gerado
     */
    public function store(array $file, string $folder, int $maxSide = 1920, ?int $thumbSide = null): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Arquivo maior que o permitido.',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado.',
                default => 'Falha no envio do arquivo.',
            });
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp) && PHP_SAPI !== 'cli') {
            throw new RuntimeException('Upload inválido.');
        }
        return $this->storeFromPath($tmp, $folder, $maxSide, $thumbSide, (int) ($file['size'] ?? 0));
    }

    /** Também usado para baixar imagens do Instagram. */
    public function storeFromPath(string $path, string $folder, int $maxSide = 1920, ?int $thumbSide = null, int $size = 0): string
    {
        if (!preg_match('/^[a-z]+$/', $folder)) {
            throw new RuntimeException('Pasta inválida.');
        }
        $size = $size ?: (int) @filesize($path);
        if ($size <= 0 || $size > $this->maxBytes) {
            throw new RuntimeException(sprintf('A imagem deve ter até %d MB.', (int) ($this->maxBytes / 1048576)));
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        if (!isset(self::ALLOWED[$mime])) {
            throw new RuntimeException('Formato não permitido. Envie JPG, PNG, WEBP ou GIF.');
        }
        $info = @getimagesize($path);
        if ($info === false || $info[2] !== self::ALLOWED[$mime]) {
            throw new RuntimeException('O arquivo não é uma imagem válida.');
        }
        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS) {
            throw new RuntimeException('Resolução da imagem não suportada.');
        }

        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
        };
        if (!$image) {
            throw new RuntimeException('Não foi possível processar a imagem.');
        }
        if ($mime === 'image/jpeg') {
            $image = $this->fixOrientation($image, $path);
        }

        $dir = $this->uploadRoot . '/' . $folder;
        if (!is_dir($dir . '/thumbs')) {
            mkdir($dir . '/thumbs', 0755, true);
        }
        $name = bin2hex(random_bytes(16)) . '.jpg';

        $this->saveResized($image, $dir . '/' . $name, $maxSide);
        if ($thumbSide !== null) {
            $this->saveResized($image, $dir . '/thumbs/' . $name, $thumbSide, 80);
        }
        imagedestroy($image);
        return $name;
    }

    public function delete(string $folder, ?string $name): void
    {
        if ($name === null || !preg_match('/^[a-f0-9]{32}\.jpg$/', $name) || !preg_match('/^[a-z]+$/', $folder)) {
            return;
        }
        foreach ([$this->uploadRoot . "/$folder/$name", $this->uploadRoot . "/$folder/thumbs/$name"] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }

    private function saveResized(\GdImage $src, string $target, int $maxSide, int $quality = 85): void
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $maxSide / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        // fundo branco para PNG/GIF transparentes
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imageinterlace($dst, true);
        if (!imagejpeg($dst, $target, $quality)) {
            imagedestroy($dst);
            throw new RuntimeException('Falha ao salvar a imagem.');
        }
        imagedestroy($dst);
        @chmod($target, 0644);
    }

    private function fixOrientation(\GdImage $image, string $path): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };
        if ($rotated instanceof \GdImage) {
            imagedestroy($image);
            return $rotated;
        }
        return $image;
    }
}
