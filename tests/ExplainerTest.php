<?php
/** Plain-language explanations: pattern coverage, locales, multibyte. */
class ExplainerTest extends PHPUnit\Framework\TestCase {

	public function test_undefined_function_ru() {
		$e = WPCA_Explainer::explain( 'Uncaught Error: Call to undefined function ss_slider_init() ', 'ru' );
		$this->assertStringContainsString( 'несуществующая функция', $e['title'] );
		$this->assertStringContainsString( 'ss_slider_init', $e['title'] );
		$this->assertStringContainsString( 'конфликт версий', $e['hint'] );
	}

	public function test_undefined_function_en() {
		$e = WPCA_Explainer::explain( 'Uncaught Error: Call to undefined function ss_slider_init()', 'en_US' );
		$this->assertStringContainsString( 'undefined function', $e['title'] );
		$this->assertStringContainsString( 'Roll back', $e['hint'] );
	}

	public function test_undefined_method() {
		$e = WPCA_Explainer::explain( 'Call to undefined method Widget::render2()', 'en' );
		$this->assertStringContainsString( 'undefined method', $e['title'] );
	}

	public function test_class_not_found() {
		$e = WPCA_Explainer::explain( 'Uncaught Error: Class "GuzzleHttp\Client" not found', 'en' );
		$this->assertStringContainsString( 'not found', $e['title'] );
		$this->assertStringContainsString( 'GuzzleHttp\Client', $e['title'] );
	}

	public function test_memory_exhausted() {
		$e = WPCA_Explainer::explain( 'Allowed memory size of 134217728 bytes exhausted', 'en' );
		$this->assertStringContainsString( 'memory', strtolower( $e['title'] ) );
		$this->assertStringContainsString( 'memory_limit', $e['hint'] );
	}

	public function test_max_execution_time() {
		$e = WPCA_Explainer::explain( 'Maximum execution time of 30 seconds exceeded', 'ru' );
		$this->assertStringContainsString( 'время выполнения', $e['title'] );
	}

	public function test_syntax_error() {
		$e = WPCA_Explainer::explain( 'Parse error: syntax error, unexpected end of file', 'en' );
		$this->assertStringContainsString( 'syntax error', strtolower( $e['title'] ) );
	}

	public function test_missing_table() {
		$e = WPCA_Explainer::explain( 'Table wp.wp_my_table doesn\'t exist on query', 'en' );
		$this->assertStringContainsString( 'missing', strtolower( $e['title'] ) );
	}

	public function test_fallback_for_unknown_message() {
		$e = WPCA_Explainer::explain( 'Совершенно новая ошибка', 'en' );
		$this->assertSame( 'PHP fatal error', $e['title'] );
	}

	public function test_multibyte_in_message_does_not_break_explain() {
		$e = WPCA_Explainer::explain( 'Call to undefined function плагин_функция()', 'ru' );
		$this->assertStringContainsString( 'плагин_функция', $e['title'] );
	}
}
