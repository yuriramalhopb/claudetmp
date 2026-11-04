<?php
/**
 * Lista de bandeiras do jogo.
 *
 * Países: os 193 membros da ONU + Vaticano e Palestina (observadores) = 195.
 * Estados: as 26 unidades federativas do Brasil + Distrito Federal = 27.
 *
 * Cada item: código => [nome, grupo]. O código é o nome do arquivo SVG em
 * bandeiras/paises/ ou bandeiras/estados/ (em minúsculas).
 */
declare(strict_types=1);

const GRUPOS = [
    'africa'       => ['nome' => 'África',                          'emoji' => '🌍', 'tipo' => 'pais'],
    'america-sul'  => ['nome' => 'América do Sul',                  'emoji' => '🌎', 'tipo' => 'pais'],
    'america-norte'=> ['nome' => 'América do Norte, Central e Caribe', 'emoji' => '🌎', 'tipo' => 'pais'],
    'asia'         => ['nome' => 'Ásia',                            'emoji' => '🌏', 'tipo' => 'pais'],
    'europa'       => ['nome' => 'Europa',                          'emoji' => '🌍', 'tipo' => 'pais'],
    'oceania'      => ['nome' => 'Oceania',                         'emoji' => '🌏', 'tipo' => 'pais'],
    'brasil'       => ['nome' => 'Estados do Brasil',               'emoji' => '🇧🇷', 'tipo' => 'estado'],
];

const PAISES = [
    // África (54)
    'ZA' => 'África do Sul', 'AO' => 'Angola', 'DZ' => 'Argélia', 'BJ' => 'Benin', 'BW' => 'Botsuana',
    'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'CV' => 'Cabo Verde', 'CM' => 'Camarões', 'TD' => 'Chade',
    'KM' => 'Comores', 'CG' => 'República do Congo', 'CD' => 'República Democrática do Congo',
    'CI' => 'Costa do Marfim', 'DJ' => 'Djibuti', 'EG' => 'Egito', 'ER' => 'Eritreia', 'SZ' => 'Essuatíni',
    'ET' => 'Etiópia', 'GA' => 'Gabão', 'GM' => 'Gâmbia', 'GH' => 'Gana', 'GN' => 'Guiné',
    'GW' => 'Guiné-Bissau', 'GQ' => 'Guiné Equatorial', 'LS' => 'Lesoto', 'LR' => 'Libéria', 'LY' => 'Líbia',
    'MG' => 'Madagascar', 'MW' => 'Malaui', 'ML' => 'Mali', 'MA' => 'Marrocos', 'MU' => 'Maurício',
    'MR' => 'Mauritânia', 'MZ' => 'Moçambique', 'NA' => 'Namíbia', 'NE' => 'Níger', 'NG' => 'Nigéria',
    'KE' => 'Quênia', 'CF' => 'República Centro-Africana', 'RW' => 'Ruanda', 'ST' => 'São Tomé e Príncipe',
    'SN' => 'Senegal', 'SL' => 'Serra Leoa', 'SC' => 'Seicheles', 'SO' => 'Somália', 'SD' => 'Sudão',
    'SS' => 'Sudão do Sul', 'TZ' => 'Tanzânia', 'TG' => 'Togo', 'TN' => 'Tunísia', 'UG' => 'Uganda',
    'ZM' => 'Zâmbia', 'ZW' => 'Zimbábue',

    // América do Sul (12)
    'AR' => 'Argentina', 'BO' => 'Bolívia', 'BR' => 'Brasil', 'CL' => 'Chile', 'CO' => 'Colômbia',
    'EC' => 'Equador', 'GY' => 'Guiana', 'PY' => 'Paraguai', 'PE' => 'Peru', 'SR' => 'Suriname',
    'UY' => 'Uruguai', 'VE' => 'Venezuela',

    // América do Norte, Central e Caribe (23)
    'AG' => 'Antígua e Barbuda', 'BS' => 'Bahamas', 'BB' => 'Barbados', 'BZ' => 'Belize', 'CA' => 'Canadá',
    'CR' => 'Costa Rica', 'CU' => 'Cuba', 'DM' => 'Dominica', 'SV' => 'El Salvador', 'US' => 'Estados Unidos',
    'GD' => 'Granada', 'GT' => 'Guatemala', 'HT' => 'Haiti', 'HN' => 'Honduras', 'JM' => 'Jamaica',
    'MX' => 'México', 'NI' => 'Nicarágua', 'PA' => 'Panamá', 'DO' => 'República Dominicana',
    'LC' => 'Santa Lúcia', 'KN' => 'São Cristóvão e Névis', 'VC' => 'São Vicente e Granadinas',
    'TT' => 'Trinidad e Tobago',

    // Ásia (47)
    'AF' => 'Afeganistão', 'SA' => 'Arábia Saudita', 'AM' => 'Armênia', 'AZ' => 'Azerbaijão', 'BH' => 'Bahrein',
    'BD' => 'Bangladesh', 'BN' => 'Brunei', 'BT' => 'Butão', 'KH' => 'Camboja', 'KZ' => 'Cazaquistão',
    'QA' => 'Catar', 'CN' => 'China', 'KP' => 'Coreia do Norte', 'KR' => 'Coreia do Sul',
    'AE' => 'Emirados Árabes Unidos', 'PH' => 'Filipinas', 'GE' => 'Geórgia', 'YE' => 'Iêmen', 'IN' => 'Índia',
    'ID' => 'Indonésia', 'IR' => 'Irã', 'IQ' => 'Iraque', 'IL' => 'Israel', 'JP' => 'Japão', 'JO' => 'Jordânia',
    'KW' => 'Kuwait', 'LA' => 'Laos', 'LB' => 'Líbano', 'MY' => 'Malásia', 'MV' => 'Maldivas', 'MM' => 'Mianmar',
    'MN' => 'Mongólia', 'NP' => 'Nepal', 'OM' => 'Omã', 'PS' => 'Palestina', 'PK' => 'Paquistão',
    'KG' => 'Quirguistão', 'SG' => 'Singapura', 'SY' => 'Síria', 'LK' => 'Sri Lanka', 'TJ' => 'Tadjiquistão',
    'TH' => 'Tailândia', 'TL' => 'Timor-Leste', 'TM' => 'Turcomenistão', 'TR' => 'Turquia',
    'UZ' => 'Uzbequistão', 'VN' => 'Vietnã',

    // Europa (45)
    'AL' => 'Albânia', 'DE' => 'Alemanha', 'AD' => 'Andorra', 'AT' => 'Áustria', 'BE' => 'Bélgica',
    'BY' => 'Bielorrússia', 'BA' => 'Bósnia e Herzegovina', 'BG' => 'Bulgária', 'CY' => 'Chipre',
    'HR' => 'Croácia', 'DK' => 'Dinamarca', 'SK' => 'Eslováquia', 'SI' => 'Eslovênia', 'ES' => 'Espanha',
    'EE' => 'Estônia', 'FI' => 'Finlândia', 'FR' => 'França', 'GR' => 'Grécia', 'HU' => 'Hungria',
    'IE' => 'Irlanda', 'IS' => 'Islândia', 'IT' => 'Itália', 'LV' => 'Letônia', 'LI' => 'Liechtenstein',
    'LT' => 'Lituânia', 'LU' => 'Luxemburgo', 'MK' => 'Macedônia do Norte', 'MT' => 'Malta',
    'MD' => 'Moldávia', 'MC' => 'Mônaco', 'ME' => 'Montenegro', 'NO' => 'Noruega', 'NL' => 'Países Baixos',
    'PL' => 'Polônia', 'PT' => 'Portugal', 'GB' => 'Reino Unido', 'CZ' => 'República Tcheca',
    'RO' => 'Romênia', 'RU' => 'Rússia', 'SM' => 'San Marino', 'RS' => 'Sérvia', 'SE' => 'Suécia',
    'CH' => 'Suíça', 'UA' => 'Ucrânia', 'VA' => 'Vaticano',

    // Oceania (14)
    'AU' => 'Austrália', 'FJ' => 'Fiji', 'MH' => 'Ilhas Marshall', 'SB' => 'Ilhas Salomão', 'KI' => 'Kiribati',
    'FM' => 'Micronésia', 'NR' => 'Nauru', 'NZ' => 'Nova Zelândia', 'PW' => 'Palau', 'PG' => 'Papua-Nova Guiné',
    'WS' => 'Samoa', 'TO' => 'Tonga', 'TV' => 'Tuvalu', 'VU' => 'Vanuatu',
];

/** Continente de cada país (a ordem segue os blocos acima). */
const CONTINENTES = [
    'africa' => ['ZA','AO','DZ','BJ','BW','BF','BI','CV','CM','TD','KM','CG','CD','CI','DJ','EG','ER','SZ','ET',
                 'GA','GM','GH','GN','GW','GQ','LS','LR','LY','MG','MW','ML','MA','MU','MR','MZ','NA','NE','NG',
                 'KE','CF','RW','ST','SN','SL','SC','SO','SD','SS','TZ','TG','TN','UG','ZM','ZW'],
    'america-sul' => ['AR','BO','BR','CL','CO','EC','GY','PY','PE','SR','UY','VE'],
    'america-norte' => ['AG','BS','BB','BZ','CA','CR','CU','DM','SV','US','GD','GT','HT','HN','JM','MX','NI','PA',
                        'DO','LC','KN','VC','TT'],
    'asia' => ['AF','SA','AM','AZ','BH','BD','BN','BT','KH','KZ','QA','CN','KP','KR','AE','PH','GE','YE','IN','ID',
               'IR','IQ','IL','JP','JO','KW','LA','LB','MY','MV','MM','MN','NP','OM','PS','PK','KG','SG','SY','LK',
               'TJ','TH','TL','TM','TR','UZ','VN'],
    'europa' => ['AL','DE','AD','AT','BE','BY','BA','BG','CY','HR','DK','SK','SI','ES','EE','FI','FR','GR','HU','IE',
                 'IS','IT','LV','LI','LT','LU','MK','MT','MD','MC','ME','NO','NL','PL','PT','GB','CZ','RO','RU','SM',
                 'RS','SE','CH','UA','VA'],
    'oceania' => ['AU','FJ','MH','SB','KI','FM','NR','NZ','PW','PG','WS','TO','TV','VU'],
];

/** Estados do Brasil: sigla => [nome, região, capital]. */
const ESTADOS = [
    'AC' => ['Acre', 'Norte', 'Rio Branco'],
    'AL' => ['Alagoas', 'Nordeste', 'Maceió'],
    'AP' => ['Amapá', 'Norte', 'Macapá'],
    'AM' => ['Amazonas', 'Norte', 'Manaus'],
    'BA' => ['Bahia', 'Nordeste', 'Salvador'],
    'CE' => ['Ceará', 'Nordeste', 'Fortaleza'],
    'DF' => ['Distrito Federal', 'Centro-Oeste', 'Brasília'],
    'ES' => ['Espírito Santo', 'Sudeste', 'Vitória'],
    'GO' => ['Goiás', 'Centro-Oeste', 'Goiânia'],
    'MA' => ['Maranhão', 'Nordeste', 'São Luís'],
    'MT' => ['Mato Grosso', 'Centro-Oeste', 'Cuiabá'],
    'MS' => ['Mato Grosso do Sul', 'Centro-Oeste', 'Campo Grande'],
    'MG' => ['Minas Gerais', 'Sudeste', 'Belo Horizonte'],
    'PA' => ['Pará', 'Norte', 'Belém'],
    'PB' => ['Paraíba', 'Nordeste', 'João Pessoa'],
    'PR' => ['Paraná', 'Sul', 'Curitiba'],
    'PE' => ['Pernambuco', 'Nordeste', 'Recife'],
    'PI' => ['Piauí', 'Nordeste', 'Teresina'],
    'RJ' => ['Rio de Janeiro', 'Sudeste', 'Rio de Janeiro'],
    'RN' => ['Rio Grande do Norte', 'Nordeste', 'Natal'],
    'RS' => ['Rio Grande do Sul', 'Sul', 'Porto Alegre'],
    'RO' => ['Rondônia', 'Norte', 'Porto Velho'],
    'RR' => ['Roraima', 'Norte', 'Boa Vista'],
    'SC' => ['Santa Catarina', 'Sul', 'Florianópolis'],
    'SP' => ['São Paulo', 'Sudeste', 'São Paulo'],
    'SE' => ['Sergipe', 'Nordeste', 'Aracaju'],
    'TO' => ['Tocantins', 'Norte', 'Palmas'],
];

/** Bandeiras quase iguais: nunca aparecem juntas como opções. */
const PARECIDAS = [
    ['TD', 'RO'], // Chade e Romênia
    ['ID', 'MC'], // Indonésia e Mônaco
];

/**
 * Monta a lista completa de itens do jogo.
 * Chave: "p-br" (país) ou "e-sp" (estado).
 *
 * @return array<string, array{nome:string, grupo:string, sub:string, info:string, img:string}>
 */
function todosOsItens(): array
{
    static $itens = null;
    if ($itens !== null) {
        return $itens;
    }

    $itens = [];
    foreach (CONTINENTES as $grupo => $codigos) {
        foreach ($codigos as $c) {
            $itens['p-' . strtolower($c)] = [
                'nome'  => PAISES[$c],
                'grupo' => $grupo,
                'sub'   => $grupo,
                'info'  => 'Continente: ' . GRUPOS[$grupo]['nome'],
                'img'   => 'bandeiras/paises/' . strtolower($c) . '.svg',
            ];
        }
    }
    foreach (ESTADOS as $uf => [$nome, $regiao, $capital]) {
        $itens['e-' . strtolower($uf)] = [
            'nome'  => $nome,
            'grupo' => 'brasil',
            'sub'   => $regiao,
            'info'  => "Região {$regiao} · Capital: {$capital} · Sigla: {$uf}",
            'img'   => 'bandeiras/estados/' . strtolower($uf) . '.svg',
        ];
    }
    return $itens;
}
