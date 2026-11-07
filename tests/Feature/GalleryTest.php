<?php
declare(strict_types=1);

namespace Tests\Feature;

final class GalleryTest extends FeatureTestCase
{
    public function testFotoDeTrilheiroPrecisaDeAprovacaoEArquivoFalsoEBloqueado(): void
    {
        $album = self::$app->model(\App\Models\Album::class)->create('Álbum Teste', null, null);
        $this->member('foto@teste.local');
        $h = $this->login('foto@teste.local');
        $h->post('/galeria/enviar', ['album_id' => (string) $album, 'photos[0]' => $this->jpeg('a.jpg'),
            'photos[1]' => $this->file("\xFF\xD8\xFF\xE0<?php system('id'); ?>", 'shell.php.jpg', 'image/jpeg')], '/galeria/enviar');
        $this->assertContains('após aprovação', $h->flashText());
        $this->assertContains('não é uma imagem válida', $h->flashText(), 'arquivo malicioso recusado');
        $photo = self::$app->db->fetch('SELECT * FROM photos WHERE album_id = ?', [$album]);
        $this->assertSame('pending', $photo['status']);
        $this->assertSame(404, $this->http()->get('/fotos/' . $photo['id'])->status, 'pendente não é pública');
        $a = $this->admin();
        $a->post('/admin/galeria/fotos/' . $photo['id'] . '/aprovar', [], '/admin/galeria');
        $this->assertSame(200, $this->http()->get('/fotos/' . $photo['id'])->status);
    }

    public function testCurtirComentarMarcarEPermissoes(): void
    {
        $album = self::$app->model(\App\Models\Album::class)->create('Álbum Social', null, null);
        $pid = self::$app->model(\App\Models\Photo::class)->create($album, str_repeat('a', 32) . '.jpg', 'Foto social', null, 'approved');
        $ana = $this->member('ana@teste.local');
        $bia = $this->member('bia@teste.local');
        $h1 = $this->login('ana@teste.local');
        $h1->post('/fotos/' . $pid . '/curtir', [], '/fotos/' . $pid);
        $this->assertContains('1 curtida', $h1->body);
        $h1->post('/fotos/' . $pid . '/comentar', ['body' => 'Top! <script>alert(1)</script>'], '/fotos/' . $pid);
        $this->assertContains('Top! &lt;script&gt;', $h1->body);
        $cid = (int) self::$app->db->fetchValue('SELECT id FROM photo_comments WHERE photo_id = ?', [$pid]);
        $h2 = $this->login('bia@teste.local');
        $h2->post('/fotos/' . $pid . '/comentarios/' . $cid . '/excluir', ['_csrf' => $h2->csrf('/fotos/' . $pid)], null, false);
        $this->assertSame(403, $h2->status, 'não apaga comentário de outro');
        $h1->post('/fotos/' . $pid . '/marcar', ['user_id' => (string) $bia['id']], '/fotos/' . $pid);
        $this->assertContains('marcado', $h1->flashText());
        $this->assertSame(1, (int) self::$app->db->fetchValue("SELECT COUNT(*) FROM notifications WHERE only_user_ids = ? AND title LIKE 'Voc%marcado%'", [(string) $bia['id']]));
        $this->assertContains('/fotos/' . $pid, $this->http()->get('/trilheiros/' . $bia['id'])->body, 'perfil mostra a foto');
        $h2->post('/fotos/' . $pid . '/marcacoes/' . $bia['id'] . '/remover', [], '/fotos/' . $pid);
        $this->assertContains('removida', $h2->flashText());
        $v = $this->http();
        $v->post('/fotos/' . $pid . '/curtir', [], '/contato', false);
        $this->assertSame(302, $v->status, 'visitante (com token válido) é mandado para o login');
        $this->assertContains('/entrar', $v->header('location'));
        $this->assertSame(1, (int) self::$app->db->fetchValue('SELECT COUNT(*) FROM photo_likes WHERE photo_id = ?', [$pid]), 'curtida do visitante não registrada');
    }
}
