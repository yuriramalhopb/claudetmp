<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Setting;

/**
 * Conteúdo inicial (tipos de vela, durações, orações, mensagens e horários), criado uma única vez.
 * Tudo pode ser editado ou apagado depois em Admin. Os horários de missa são só um exemplo.
 */
final class Seeder
{
    public function __construct(private readonly Database $db, private readonly Setting $settings)
    {
    }

    public function run(): bool
    {
        if ($this->settings->get('seeded_at') !== null && $this->settings->get('seeded_at') !== '') {
            return false;
        }
        $now = date('Y-m-d H:i:s');

        $this->db->transaction(function () use ($now) {
            if ($this->empty('candle_types')) {
                $types = [
                    ['Vela Branca', '#F7F2E8', 'Paz, fé e gratidão', 0],
                    ['Vela Azul', '#5B8DEF', 'Proteção e confiança em Nossa Senhora', 0],
                    ['Vela Verde', '#5CB85C', 'Esperança e saúde', 0],
                    ['Vela Vermelha', '#E0524D', 'Amor, fortaleza e Espírito Santo', 0],
                    ['Vela Roxa', '#8E5BD6', 'Conversão, perdão e intercessão pelos falecidos', 0],
                    ['Vela Dourada', '#E8B84A', 'Vela especial: sua oferta ajuda a manter a igreja', 500],
                ];
                foreach ($types as $i => [$name, $color, $desc, $price]) {
                    $this->db->insert('candle_types', ['name' => $name, 'color' => $color, 'description' => $desc, 'price' => $price, 'sort_order' => $i, 'active' => 1, 'created_at' => $now]);
                }
            }
            if ($this->empty('candle_plans')) {
                $plans = [['24 horas', 24, 0, 0], ['3 dias', 72, 0, 0], ['7 dias', 168, 500, 0], ['15 dias', 360, 1000, 0], ['30 dias', 720, 1500, 1]];
                foreach ($plans as $i => [$label, $hours, $price, $hl]) {
                    $this->db->insert('candle_plans', ['label' => $label, 'hours' => $hours, 'price' => $price, 'highlight' => $hl, 'sort_order' => $i, 'active' => 1, 'created_at' => $now]);
                }
            }
            if ($this->empty('prayers')) {
                foreach (self::prayers() as $i => [$title, $body]) {
                    $this->db->insert('prayers', ['title' => $title, 'slug' => slugify($title), 'body' => $body, 'sort_order' => $i, 'active' => 1, 'created_at' => $now]);
                }
            }
            if ($this->empty('daily_messages')) {
                foreach (self::messages() as [$text, $ref]) {
                    $this->db->insert('daily_messages', ['text' => $text, 'reference' => $ref, 'show_on' => null, 'active' => 1, 'created_at' => $now]);
                }
            }
            if ($this->empty('mass_times')) {
                $times = [
                    [0, '08:00', 'Santa Missa', 'Igreja Matriz'],
                    [0, '10:00', 'Santa Missa', 'Igreja Matriz'],
                    [0, '19:00', 'Santa Missa', 'Igreja Matriz'],
                    [3, '19:30', 'Santa Missa', 'Igreja Matriz'],
                    [5, '19:30', 'Santa Missa', 'Igreja Matriz'],
                ];
                foreach ($times as $i => [$wd, $time, $label, $place]) {
                    $this->db->insert('mass_times', ['weekday' => $wd, 'time' => $time, 'label' => $label, 'location' => $place, 'sort_order' => $i, 'active' => 1, 'created_at' => $now]);
                }
            }
        });
        $this->settings->set('seeded_at', $now);
        return true;
    }

    private function empty(string $table): bool
    {
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM {$table}") === 0;
    }

    /** @return array<int, array{0:string,1:string}> */
    public static function prayers(): array
    {
        return [
            ['Oração diante da vela', "Senhor, esta pequena luz representa a minha fé.\nEnquanto ela arde, confio a Ti as minhas preocupações, as minhas esperanças e as pessoas que amo.\nQue a Tua luz dissipe as nossas trevas, fortaleça a nossa esperança e nos conduza pelo caminho da paz.\nEu não sei como, mas confio que Tu sabes o que é melhor para cada um de nós.\nFaça-se em nós a Tua vontade. Amém."],
            ['Pai-Nosso', "Pai nosso que estais nos céus,\nsantificado seja o vosso nome,\nvenha a nós o vosso reino,\nseja feita a vossa vontade\nassim na terra como no céu.\nO pão nosso de cada dia nos dai hoje,\nperdoai-nos as nossas ofensas\nassim como nós perdoamos a quem nos tem ofendido,\ne não nos deixeis cair em tentação,\nmas livrai-nos do mal. Amém."],
            ['Ave-Maria', "Ave Maria, cheia de graça,\no Senhor é convosco,\nbendita sois vós entre as mulheres\ne bendito é o fruto do vosso ventre, Jesus.\nSanta Maria, Mãe de Deus,\nrogai por nós, pecadores,\nagora e na hora da nossa morte. Amém."],
            ['Glória ao Pai', "Glória ao Pai, ao Filho e ao Espírito Santo.\nAssim como era no princípio, agora e sempre. Amém."],
            ['Salve-Rainha', "Salve, Rainha, Mãe de misericórdia,\nvida, doçura e esperança nossa, salve!\nA vós bradamos, os degredados filhos de Eva;\na vós suspiramos, gemendo e chorando neste vale de lágrimas.\nEia, pois, advogada nossa, esses vossos olhos misericordiosos a nós volvei;\ne depois deste desterro mostrai-nos Jesus, bendito fruto do vosso ventre,\nó clemente, ó piedosa, ó doce sempre Virgem Maria.\nRogai por nós, santa Mãe de Deus,\npara que sejamos dignos das promessas de Cristo. Amém."],
            ['Credo', "Creio em Deus Pai todo-poderoso, Criador do céu e da terra;\ne em Jesus Cristo, seu único Filho, nosso Senhor,\nque foi concebido pelo poder do Espírito Santo,\nnasceu da Virgem Maria,\npadeceu sob Pôncio Pilatos,\nfoi crucificado, morto e sepultado,\ndesceu à mansão dos mortos,\nressuscitou ao terceiro dia,\nsubiu aos céus,\nestá sentado à direita de Deus Pai todo-poderoso,\ndonde há de vir a julgar os vivos e os mortos.\nCreio no Espírito Santo,\nna santa Igreja Católica,\nna comunhão dos santos,\nna remissão dos pecados,\nna ressurreição da carne,\nna vida eterna. Amém."],
            ['Oração ao Anjo da Guarda', "Santo Anjo do Senhor,\nmeu zeloso guardador,\nse a ti me confiou a piedade divina,\nsempre me rege, guarda, governa e ilumina. Amém."],
            ['Oração de São Francisco', "Senhor, fazei de mim um instrumento de vossa paz.\nOnde houver ódio, que eu leve o amor;\nonde houver ofensa, que eu leve o perdão;\nonde houver discórdia, que eu leve a união;\nonde houver dúvida, que eu leve a fé;\nonde houver erro, que eu leve a verdade;\nonde houver desespero, que eu leve a esperança;\nonde houver tristeza, que eu leve a alegria;\nonde houver trevas, que eu leve a luz.\nÓ Mestre, fazei que eu procure mais consolar que ser consolado;\ncompreender que ser compreendido;\namar que ser amado.\nPois é dando que se recebe,\nperdoando que se é perdoado,\ne morrendo que se vive para a vida eterna. Amém."],
            ['Oração pelos falecidos', "Dai-lhes, Senhor, o descanso eterno,\ne a luz perpétua os ilumine.\nDescansem em paz. Amém."],
            ['Oração pelos doentes', "Senhor Jesus, que passastes pelo mundo fazendo o bem e curando os enfermos,\nolhai com bondade para os nossos doentes.\nDai-lhes alívio na dor, paciência na provação e esperança na cura.\nFortalecei os médicos e cuidadores, e consolai as famílias.\nQue sintam a Vossa presença e a nossa oração. Amém."],
        ];
    }

    /** @return array<int, array{0:string,1:string}> */
    public static function messages(): array
    {
        return [
            ['O Senhor é o meu pastor; nada me faltará.', 'Salmo 23,1'],
            ['Tudo posso naquele que me fortalece.', 'Filipenses 4,13'],
            ['Não temas, porque eu sou contigo; não te assombres, porque eu sou o teu Deus.', 'Isaías 41,10'],
            ['Vinde a mim, todos os que estais cansados e oprimidos, e eu vos aliviarei.', 'Mateus 11,28'],
            ['Eu sou a luz do mundo; quem me segue não andará em trevas, mas terá a luz da vida.', 'João 8,12'],
            ['Confia no Senhor de todo o teu coração e não te estribes no teu próprio entendimento.', 'Provérbios 3,5'],
            ['O Senhor é a minha luz e a minha salvação; a quem temerei?', 'Salmo 27,1'],
            ['Pedi, e dar-se-vos-á; buscai, e encontrareis; batei, e abrir-se-vos-á.', 'Mateus 7,7'],
            ['Lança sobre o Senhor o teu cuidado, e ele te sustentará.', 'Salmo 55,22'],
            ['Bem-aventurados os pacificadores, porque serão chamados filhos de Deus.', 'Mateus 5,9'],
            ['Deixo-vos a paz, a minha paz vos dou; não vo-la dou como o mundo a dá.', 'João 14,27'],
            ['Alegrai-vos sempre no Senhor; outra vez digo: alegrai-vos.', 'Filipenses 4,4'],
            ['Levanto os meus olhos para os montes: de onde me virá o socorro? O meu socorro vem do Senhor, que fez os céus e a terra.', 'Salmo 121,1-2'],
            ['A tua palavra é lâmpada para os meus pés e luz para o meu caminho.', 'Salmo 119,105'],
            ['Vós sois a luz do mundo. Não se pode esconder uma cidade situada sobre um monte.', 'Mateus 5,14'],
            ['Perto está o Senhor de todos os que o invocam, de todos os que o invocam em verdade.', 'Salmo 145,18'],
            ['Eu vim para que tenham vida, e a tenham em abundância.', 'João 10,10'],
            ['Esforça-te e tem bom ânimo; não temas, porque o Senhor teu Deus é contigo por onde quer que andares.', 'Josué 1,9'],
        ];
    }
}
