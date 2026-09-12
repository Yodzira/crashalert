<?php
/**
 * Plain-language explanations of common PHP fatals, RU and EN.
 * Each pattern maps to a human title and a short first-aid hint.
 * The first matching pattern wins; patterns are ordered from the most
 * specific to the most generic.
 */

defined( 'ABSPATH' ) || exit;

class WPCA_Explainer {

	/**
	 * @return array {pattern: string(regexp), ru: [title, hint], en: [title, hint]}
	 */
	public static function patterns() {
		return array(
			array(
				'pattern' => '/Call to undefined function\s+(\S+)\s*\(\)/i',
				'ru'      => array( 'Вызвана несуществующая функция «%s»',
					'Обычно это конфликт версий: плагин обновился частично или зависит от другого плагина. Откатите последнее обновление или отключите виновника.' ),
				'en'      => array( 'Call to undefined function «%s»',
					'Usually a version conflict: a plugin was partially updated or depends on another plugin. Roll back the last update or deactivate the culprit.' ),
			),
			array(
				'pattern' => '/Call to undefined method\s+(\S+)/i',
				'ru'      => array( 'Вызван несуществующий метод «%s»',
					'Версии плагинов разошлись: код ждёт метод, которого в установленной версии нет. Обновите или откатите виновника целиком.' ),
				'en'      => array( 'Call to undefined method «%s»',
					'Plugin versions diverged: the code expects a method that does not exist in the installed version. Fully update or roll back the culprit.' ),
			),
			array(
				'pattern' => '/Class ["\\\']?([\w\\\\]+)["\\\']? not found/i',
				'ru'      => array( 'Не найден класс «%s»',
					'Класс пропал после обновления или файл автозагрузки не подключился. Переустановите виновника из официального источника.' ),
				'en'      => array( 'Class «%s» not found',
					'The class disappeared after an update or the autoloader file is missing. Reinstall the culprit from its official source.' ),
			),
			array(
				'pattern' => '/Allowed memory size of\s+(\d+)\s*bytes/i',
				'ru'      => array( 'Недостаточно памяти (memory exhausted)',
					'Скрипт превысил лимит памяти. Часто помогает increase memory_limit до 256M; если нет — ищите тяжёлый цикл во виновнике.' ),
				'en'      => array( 'Out of memory (memory exhausted)',
					'The script hit the memory limit. Raising memory_limit to 256M often helps; otherwise look for a heavy loop in the culprit.' ),
			),
			array(
				'pattern' => '/Maximum execution time of\s+(\d+)\s*seconds/i',
				'ru'      => array( 'Превышено время выполнения скрипта',
					'Скрипт работал слишком долго — обычно внешний API или тяжёлый запрос. Проверьте, что обновлялось, и добавьте таймауты.' ),
				'en'      => array( 'Maximum execution time exceeded',
					'The script ran too long — usually a slow external API or a heavy query. Check what changed recently and add timeouts.' ),
			),
			array(
				'pattern' => '/syntax error,\s*unexpected/i',
				'ru'      => array( 'Синтаксическая ошибка в PHP-файле',
					'Файл повреждён или обрезан (типично при оборвавшемся обновлении). Перезалейте файл виновника поверх по FTP.' ),
				'en'      => array( 'PHP syntax error in the culprit file',
					'The file is corrupted or truncated (typical for an interrupted update). Re-upload the culprit file over FTP.' ),
			),
			array(
				'pattern' => '/(Undefined property|Undefined index|Undefined variable)/i',
				'ru'      => array( 'Обращение к несуществующему свойству/индексу',
					'Код ждёт данные, которых нет при этих настройках. Строго говоря, это не фатал — значит, версия PHP строга или ошибка глубже.' ),
				'en'      => array( 'Access to an undefined property/index',
					'The code expects data that is missing under these settings. This should not be fatal by itself — check the PHP version or a deeper error.' ),
			),
			array(
				'pattern' => '/(Table|table)\s+[\w.]*\s?(doesn\'t exist|does not exist)/i',
				'ru'      => array( 'Отсутствует таблица в базе данных',
					'Таблица удалена или не создана. Деактивируйте и активируйте виновника — обычно это пересоздаёт таблицы.' ),
				'en'      => array( 'A database table is missing',
					'The table was dropped or never created. Deactivate and reactivate the culprit — that usually recreates its tables.' ),
			),
			array(
				'pattern' => '/(Access denied for user|Connection refused|MySQL server has gone away)/i',
				'ru'      => array( 'Ошибка подключения к базе данных',
					'Проблема на стороне хостинга: лимиты, рестарт MySQL или неверные реквизиты. Проверьте DB-доступы и статус MySQL.' ),
				'en'      => array( 'Database connection error',
					'A hosting-side problem: limits, MySQL restart, or wrong credentials. Check the DB settings and MySQL status.' ),
			),
		);
	}

	/**
	 * Explain an error message.
	 *
	 * @return array {title: string, hint: string}
	 */
	public static function explain( $message, $locale = '' ) {
		$locale = $locale ? $locale : ( function_exists( 'get_locale' ) ? get_locale() : 'en' );
		$is_ru  = 0 === strpos( (string) $locale, 'ru' );
		$msg    = (string) $message;

		foreach ( self::patterns() as $p ) {
			if ( preg_match( $p['pattern'], $msg, $m ) ) {
				$tpl = $is_ru ? $p['ru'] : $p['en'];
				$title = $tpl[0];
				if ( isset( $m[1] ) && false !== strpos( $title, '%s' ) ) {
					$title = sprintf( $title, $m[1] );
				}
				return array(
					'title' => $title,
					'hint'  => $tpl[1],
				);
			}
		}

		return $is_ru
			? array(
				'title' => 'Фатальная ошибка PHP',
				'hint'  => 'Причина не из списка типовых. Откройте стек в карточке сбоя — он укажет на конкретный файл и вызов.',
			)
			: array(
				'title' => 'PHP fatal error',
				'hint'  => 'This one is not in the common list. Open the stack trace in the event card — it points at the exact file and call.',
			);
	}
}
