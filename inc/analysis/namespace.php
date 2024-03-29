<?php
/**
 * Altis Search Analysis related functions.
 *
 * @package altis/enhanced-search
 */

namespace Altis\Enhanced_Search\Analysis;

use ElasticPress\Elasticsearch;

/**
 * Return the default search analyzer index settings.
 *
 * @return array
 */
function get_analyzers() : array {
	$version = Elasticsearch::factory()->get_elasticsearch_version();

	$analyzers = [
		'filter' => [
			'possessive_filter' => [
				'type' => 'stemmer',
				'name' => 'possessive_english',
			],
			'ar_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_arabic_',
				],
			],
			'ar_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'arabic',
			],
			'bg_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_bulgarian_',
				],
			],
			'bg_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'bulgarian',
			],
			'bn_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_bengali_',
				],
			],
			'bn_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'bengali',
			],
			'br_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_brazilian_',
				],
			],
			'br_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'brazilian',
			],
			'ca_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_catalan_',
				],
			],
			'ca_elision_filter' => [
				'type' => 'elision',
				'articles' => [
					'd',
					'l',
					'm',
					'n',
					's',
					't',
				],
				'articles_case' => true,
			],
			'ca_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'catalan',
			],
			'ckb_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_sorani_',
				],
			],
			'ckb_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'sorani',
			],
			'cs_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_czech_',
				],
			],
			'da_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_danish_',
				],
			],
			'da_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'danish',
			],
			'de_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_german_',
				],
			],
			'de_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'minimal_german',
			],
			'el_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_greek_',
				],
			],
			'en_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_english_',
				],
			],
			'en_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'light_english',
			],
			'es_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_spanish_',
				],
			],
			'es_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'light_spanish',
			],
			'eu_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_basque_',
				],
			],
			'fa_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_persian_',
				],
			],
			'fi_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_finnish_',
				],
			],
			'fi_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'light_finish',
			],
			'fr_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_french_',
				],
			],
			'fr_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'minimal_french',
			],
			'fr_elision_filter' => [
				'type' => 'elision',
				'articles_case' => true,
				'articles' => [
					'l',
					'm',
					't',
					'qu',
					'n',
					's',
					'j',
					'd',
					'c',
					'jusqu',
					'quoiqu',
					'lorsqu',
					'puisqu',
				],
			],
			'ga_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_irish_',
				],
			],
			'ga_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'irish',
			],
			'ga_elision_filter' => [
				'type' => 'elision',
				'articles' => [
					'h',
					'n',
					't',
				],
			],
			'gl_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_galician_',
				],
			],
			'gl_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'minimal_galician',
			],
			'greek_lowercase_filter' => [
				'type' => 'lowercase',
				'language' => 'greek',
			],
			'he_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'אבל',
					'או',
					'אולי',
					'אותה',
					'אותה',
					'אותו',
					'אותו',
					'אותו',
					'אותי',
					'אותך',
					'אותם',
					'אותן',
					'אותנו',
					'אז',
					'אחר',
					'אחר',
					'אחרות',
					'אחרי',
					'אחרי',
					'אחרי',
					'אחרים',
					'אחרת',
					'אי',
					'איזה',
					'איך',
					'אין',
					'אין',
					'איפה',
					'איתה',
					'איתו',
					'איתי',
					'איתך',
					'איתכם',
					'איתכן',
					'איתם',
					'איתן',
					'איתנו',
					'אך',
					'אך',
					'אל',
					'אל',
					'אלה',
					'אלה',
					'אלו',
					'אלו',
					'אם',
					'אם',
					'אנחנו',
					'אני',
					'אס',
					'אף',
					'אצל',
					'אשר',
					'אשר',
					'את',
					'את',
					'אתה',
					'אתכם',
					'אתכן',
					'אתם',
					'אתן',
					'באמצע',
					'באמצעות',
					'בגלל',
					'בין',
					'בלי',
					'בלי',
					'במידה',
					'ברם',
					'בשביל',
					'בתוך',
					'גם',
					'דרך',
					'הוא',
					'היא',
					'היה',
					'היכן',
					'היתה',
					'היתי',
					'הם',
					'הן',
					'הנה',
					'הרי',
					'ואילו',
					'ואת',
					'זאת',
					'זה',
					'זה',
					'זות',
					'יהיה',
					'יוכל',
					'יוכלו',
					'יותר',
					'יכול',
					'יכולה',
					'יכולות',
					'יכולים',
					'יכל',
					'יכלה',
					'יכלו',
					'יש',
					'כאן',
					'כאשר',
					'כולם',
					'כולן',
					'כזה',
					'כי',
					'כיצד',
					'כך',
					'ככה',
					'כל',
					'כלל',
					'כמו',
					'כמו',
					'כן',
					'כן',
					'כפי',
					'כש',
					'לא',
					'לאו',
					'לאן',
					'לבין',
					'לה',
					'להיות',
					'להם',
					'להן',
					'לו',
					'לי',
					'לכם',
					'לכן',
					'לכן',
					'למה',
					'למטה',
					'למעלה',
					'למרות',
					'למרות',
					'לנו',
					'לעבר',
					'לעיכן',
					'לפיכך',
					'לפני',
					'לפני',
					'מאד',
					'מאחורי',
					'מאין',
					'מאיפה',
					'מבלי',
					'מבעד',
					'מדוע',
					'מדי',
					'מה',
					'מהיכן',
					'מול',
					'מחוץ',
					'מי',
					'מכאן',
					'מכיוון',
					'מלבד',
					'מן',
					'מנין',
					'מסוגל',
					'מעט',
					'מעטים',
					'מעל',
					'מעל',
					'מצד',
					'מתחת',
					'מתחת',
					'מתי',
					'נגד',
					'נגר',
					'נו',
					'עד',
					'עד',
					'עז',
					'על',
					'על',
					'עלי',
					'עליה',
					'עליהם',
					'עליהן',
					'עליו',
					'עליך',
					'עליכם',
					'עלינו',
					'עם',
					'עם',
					'עצמה',
					'עצמהם',
					'עצמהן',
					'עצמו',
					'עצמי',
					'עצמם',
					'עצמן',
					'עצמנו',
					'פה',
					'רק',
					'רק',
					'שוב',
					'שוב',
					'של',
					'שלה',
					'שלהם',
					'שלהן',
					'שלו',
					'שלי',
					'שלך',
					'שלכם',
					'שלכן',
					'שלנו',
					'שם',
					'תהיה',
					'תחת',
				],
			],
			'hi_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_hindi_',
				],
			],
			'hu_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_hungarian_',
				],
			],
			'hu_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'light_hungarian',
			],
			'hy_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_armenian_',
				],
			],
			'hy_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'armenian',
			],
			'id_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_indonesian_',
				],
			],
			'it_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_italian_',
				],
			],
			'it_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'light_italian',
			],
			'it_elision_filter' => [
				'type' => 'elision',
				'articles' => [
					'c',
					'l',
					'all',
					'dall',
					'dell',
					'nell',
					'sull',
					'coll',
					'pell',
					'gl',
					'agl',
					'dagl',
					'degl',
					'negl',
					'sugl',
					'un',
					'm',
					't',
					's',
					'v',
					'd',
				],
				'articles_case' => true,
			],
			'ja_pos_filter' => [
				'type' => 'kuromoji_part_of_speech',
				'stoptags' => [
					'助詞-格助詞-一般',
					'助詞-終助詞',
				],
			],
			'nl_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_dutch_',
				],
			],
			'nl_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'dutch_kp',
			],
			'nl_stem_override_filter' => [
				'type' => 'stemmer_override',
				'rules' => [
					'fiets=>fiets',
					'bromfiets=>bromfiets',
					'ei=>eier',
					'kind=>kinder',
				],
			],
			'no_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_norwegian_',
				],
			],
			'nb_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'light_norwegian',
			],
			'nn_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'light_nynorsk',
			],
			'pt_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_portuguese_',
				],
			],
			'pt_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'minimal_portuguese',
			],
			'ro_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_romanian_',
				],
			],
			'ru_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_russian_',
				],
			],
			'ru_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'light_russian',
			],
			'sv_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_swedish_',
				],
			],
			'sv_stem_filter' => [
				'type' => 'stemmer',
				'name' => 'light_swedish',
			],
			'tr_stop_filter' => [
				'type' => 'stop',
				'stopwords' => [
					'_turkish_',
				],
			],
			'tr_lowercase_filter' => [
				'type' => 'lowercase',
				'language' => 'turkish',
			],
			'tr_stem_filter' => [
				'type' => 'stemmer',
				'language' => 'turkish',
			],
		],
		'char_filter' => [
			'de_char_filter' => [
				'type' => 'mapping',
				'mappings' => [
					'ß=>ss',
					'Ä=>ae',
					'ä=>ae',
					'Ö=>oe',
					'ö=>oe',
					'Ü=>ue',
					'ü=>ue',
					'ph=>f',
				],
			],
			'zero_width_spaces' => [
				'type' => 'mapping',
				'mappings' => [
					'\\u200C=> ',
				],
			],
		],
		'analyzer' => [
			'ar_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'ar_stop_filter',
					'arabic_normalization',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'bg_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'bg_stop_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'bn_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'indic_normalization',
					'bengali_normalization',
					'bn_stop_filter',
					'bn_stem_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'br_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'br_stop_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'ca_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'ca_elision_filter',
					'lowercase',
					'icu_normalizer',
					'ca_stop_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'ckb_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'sorani_normalization',
					'ckb_stop_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'cs_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'cs_stop_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'da_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'da_stop_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'de_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'de_stop_filter',
					'german_normalization',
					'de_stem_filter',
					'icu_folding',
				],
				'char_filter' => [
					'de_char_filter',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'el_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'greek_lowercase_filter',
					'el_stop_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'en_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'en_stop_filter',
					'possessive_filter',
					'en_stem_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'es_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'es_stop_filter',
					'es_stem_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'eu_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'eu_stop_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'fa_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'arabic_normalization',
					'persian_normalization',
					'fa_stop_filter',
					'icu_folding',
				],
				'char_filter' => [
					'zero_width_spaces',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'fi_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'fi_stop_filter',
					'fi_stem_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'fr_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'fr_elision_filter',
					'fr_stop_filter',
					'fr_stem_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'ga_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'ga_elision_filter',
					'ga_stop_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'he_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'he_stop_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'hi_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'indic_normalization',
					'hindi_normalization',
					'hi_stop_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'hu_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'hu_stop_filter',
					'hu_stem_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'hy_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'hy_stop_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'id_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'id_stop_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'it_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'it_elision_filter',
					'it_stop_filter',
					'it_stem_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'ja_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'kuromoji_baseform',
					'ja_pos_filter',
					'icu_normalizer',
					'icu_folding',
				],
				'tokenizer' => 'kuromoji',
			],
			'ko_analyzer' => [
				'type' => 'cjk',
				'filter' => [],
			],
			'nl_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'nl_stop_filter',
					'nl_stem_override_filter',
					'nl_stem_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'nb_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'no_stop_filter',
					'nb_stem_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'nn_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'no_stop_filter',
					'nn_stem_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'pl_analyzer' => [
				'type' => 'polish',
			],
			'pt_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'pt_stop_filter',
					'pt_stem_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'ro_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'ro_stop_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'ru_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'ru_stop_filter',
					'ru_stem_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'sv_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'sv_stop_filter',
					'sv_stem_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'th_analyzer' => [
				'type' => 'thai',
			],
			'tr_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'apostrophe',
					'tr_lowercase_filter',
					'tr_stop_filter',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'ua_analyzer' => [
				'type' => 'ukrainian',
			],
			'zh_analyzer' => [
				'type' => 'smartcn',
			],
			'lowercase_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'icu_folding',
				],
				'tokenizer' => 'keyword',
			],
			'default_analyzer' => [
				'type' => 'custom',
				'filter' => [
					'icu_normalizer',
					'icu_folding',
				],
				'tokenizer' => 'icu_tokenizer',
			],
			'edge_ngram_analyzer' => [
				'type' => 'custom',
				'tokenizer' => 'icu_tokenizer',
				'filter' => [
					'icu_normalizer',
					'icu_folding',
					'edge_ngram',
				],
			],
		],
		'tokenizer' => [
			'kuromoji' => [
				'type' => 'kuromoji_tokenizer',
				'mode' => 'search',
			],
		],
	];

	return $analyzers;
}
