<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Unit\Services;

use AndyDefer\LaravelSearch\Services\TextNormalizerService;
use PHPUnit\Framework\TestCase;

final class TextNormalizerServiceTest extends TestCase
{
    private TextNormalizerService $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new TextNormalizerService;
    }

    // ============================================================
    // TESTS DE NORMALISATION
    // ============================================================

    public function test_normalize_simple_text(): void
    {
        $result = $this->normalizer->normalize('Hello World');
        $this->assertSame('hello world', $result);
    }

    public function test_normalize_uppercase_text(): void
    {
        $result = $this->normalizer->normalize('HELLO WORLD');
        $this->assertSame('hello world', $result);
    }

    public function test_normalize_with_accents(): void
    {
        $result = $this->normalizer->normalize('Café, thé, gâteau');
        $this->assertSame('cafe the gateau', $result);
    }

    public function test_normalize_french_accents(): void
    {
        $result = $this->normalizer->normalize('J\'ai mangé un croissant à Paris');
        $this->assertStringContainsString('j ai mange un croissant a paris', $result);
    }

    public function test_normalize_spanish_accents(): void
    {
        $result = $this->normalizer->normalize('España, México, Corazón');
        $this->assertSame('espana mexico corazon', $result);
    }

    public function test_normalize_german_accents(): void
    {
        $result = $this->normalizer->normalize('Müller Straße');
        $this->assertSame('muller strasse', $result);
    }

    public function test_normalize_special_characters(): void
    {
        $result = $this->normalizer->normalize('Hello @ World! $100');
        $this->assertSame('hello world usd 100', $result);
    }

    public function test_normalize_punctuation(): void
    {
        $result = $this->normalizer->normalize('This, is; a: test! with? punctuation.');
        $this->assertSame('this is a test with punctuation', $result);
    }

    public function test_normalize_multiple_spaces(): void
    {
        $result = $this->normalizer->normalize('This   has   many   spaces');
        $this->assertSame('this has many spaces', $result);
    }

    public function test_normalize_trailing_spaces(): void
    {
        $result = $this->normalizer->normalize('  trimmed  ');
        $this->assertSame('trimmed', $result);
    }

    // ============================================================
    // TESTS D'EXTRACTION DE MOTS
    // ============================================================

    public function test_extract_words_simple(): void
    {
        $result = $this->normalizer->extractWords('Hello World');
        $this->assertSame(['hello', 'world'], $result);
    }

    public function test_extract_words_with_accents(): void
    {
        $result = $this->normalizer->extractWords('Café, thé, gâteau');
        $this->assertSame(['cafe', 'the', 'gateau'], $result);
    }

    public function test_extract_words_with_special_characters(): void
    {
        $result = $this->normalizer->extractWords('Hello @ World! $100');
        $this->assertSame(['hello', 'world', 'usd', '100'], $result);
    }

    public function test_extract_words_with_punctuation(): void
    {
        $result = $this->normalizer->extractWords('This, is; a: test!');
        $this->assertSame(['this', 'is', 'a', 'test'], $result);
    }

    public function test_extract_words_empty(): void
    {
        $result = $this->normalizer->extractWords('');
        $this->assertSame([], $result);
    }

    // ============================================================
    // TESTS DE SUPPRESSION DES CARACTÈRES
    // ============================================================

    public function test_remove_diacritics_french(): void
    {
        $result = $this->normalizer->removeDiacritics('À Á Â Ã Ä Å Ç È É Ê Ë Ì Í Î Ï');
        $this->assertSame('A A A A A A C E E E E I I I I', $result);
    }

    public function test_remove_diacritics_french_lowercase(): void
    {
        $result = $this->normalizer->removeDiacritics('à á â ã ä å ç è é ê ë ì í î ï');
        $this->assertSame('a a a a a a c e e e e i i i i', $result);
    }

    public function test_remove_diacritics_spanish(): void
    {
        $result = $this->normalizer->removeDiacritics('Ñ Ó Ò Ô Õ Ö Ú Ù Û Ü');
        $this->assertSame('N O O O O O U U U U', $result);
    }

    public function test_remove_diacritics_spanish_lowercase(): void
    {
        $result = $this->normalizer->removeDiacritics('ñ ó ò ô õ ö ú ù û ü');
        $this->assertSame('n o o o o o u u u u', $result);
    }

    public function test_remove_diacritics_german(): void
    {
        $result = $this->normalizer->removeDiacritics('Ä Ö Ü ß');
        $this->assertSame('A O U ss', $result);
    }

    public function test_remove_diacritics_german_lowercase(): void
    {
        $result = $this->normalizer->removeDiacritics('ä ö ü ß');
        $this->assertSame('a o u ss', $result);
    }

    // ============================================================
    // TESTS DES SYMBOLES MONÉTAIRES
    // ============================================================

    public function test_remove_currency_symbols_euro(): void
    {
        $result = $this->normalizer->removeCurrencySymbols('€100');
        $this->assertSame(' eur 100', $result);
    }

    public function test_remove_currency_symbols_dollar(): void
    {
        $result = $this->normalizer->removeCurrencySymbols('$100');
        $this->assertSame(' usd 100', $result);
    }

    public function test_remove_currency_symbols_pound(): void
    {
        $result = $this->normalizer->removeCurrencySymbols('£50');
        $this->assertSame(' gbp 50', $result);
    }

    public function test_remove_currency_symbols_yen(): void
    {
        $result = $this->normalizer->removeCurrencySymbols('¥1000');
        $this->assertSame(' jpy 1000', $result);
    }

    // ============================================================
    // TESTS DE VÉRIFICATION
    // ============================================================

    public function test_has_accents_true(): void
    {
        $this->assertTrue($this->normalizer->hasAccents('café'));
        $this->assertTrue($this->normalizer->hasAccents('Müller'));
        $this->assertTrue($this->normalizer->hasAccents('España'));
    }

    public function test_has_accents_false(): void
    {
        $this->assertFalse($this->normalizer->hasAccents('hello'));
        $this->assertFalse($this->normalizer->hasAccents('world'));
        $this->assertFalse($this->normalizer->hasAccents('123'));
    }

    public function test_has_special_chars_true(): void
    {
        $this->assertTrue($this->normalizer->hasSpecialChars('hello@world'));
        $this->assertTrue($this->normalizer->hasSpecialChars('hello world!'));
        $this->assertTrue($this->normalizer->hasSpecialChars('$100'));
    }

    public function test_has_special_chars_false(): void
    {
        $this->assertFalse($this->normalizer->hasSpecialChars('hello'));
        $this->assertFalse($this->normalizer->hasSpecialChars('hello world'));
        $this->assertFalse($this->normalizer->hasSpecialChars('123'));
    }

    // ============================================================
    // TESTS DE SUPPRESSION DES MOTS COURTS
    // ============================================================

    public function test_remove_short_words_default(): void
    {
        $words = ['a', 'ab', 'abc', 'abcd'];
        $result = $this->normalizer->removeShortWords($words);
        $this->assertSame(['ab', 'abc', 'abcd'], $result);
    }

    public function test_remove_short_words_custom_length(): void
    {
        $words = ['a', 'ab', 'abc', 'abcd'];
        $result = $this->normalizer->removeShortWords($words, 3);
        $this->assertSame(['abc', 'abcd'], $result);
    }

    // ============================================================
    // TESTS DE NORMALISATION DES ESPACES
    // ============================================================

    public function test_normalize_spaces(): void
    {
        $result = $this->normalizer->normalizeSpaces('this   has   many   spaces');
        $this->assertSame('this has many spaces', $result);
    }

    public function test_normalize_spaces_trim(): void
    {
        $result = $this->normalizer->normalizeSpaces('  trimmed  ');
        $this->assertSame('trimmed', $result);
    }

    public function test_normalize_spaces_empty(): void
    {
        $result = $this->normalizer->normalizeSpaces('');
        $this->assertSame('', $result);
    }

    // ============================================================
    // TESTS DE CAS D'USAGE RÉELS
    // ============================================================

    public function test_normalize_website_content(): void
    {
        $text = 'Le site de la ville de Mont-Saint-Michel est magnifique !';
        $result = $this->normalizer->normalize($text);
        $this->assertStringContainsString('le site de la ville de mont saint michel est magnifique', $result);
    }

    public function test_normalize_scientific_text(): void
    {
        $text = 'Le phénomène est complexe, avec des équations ∂²/∂x².';
        $result = $this->normalizer->normalize($text);
        $this->assertStringContainsString('le phenomene est complexe avec des equations', $result);
    }

    public function test_normalize_with_currency_symbols(): void
    {
        $result = $this->normalizer->normalize('Price: €100, £50, $75');
        $this->assertStringContainsString('price eur 100 gbp 50 usd 75', $result);
    }

    public function test_normalize_cyrillic_text(): void
    {
        $result = $this->normalizer->normalize('Привет мир');
        $this->assertStringContainsString('privet mir', $result);
    }

    public function test_normalize_greek_text(): void
    {
        $result = $this->normalizer->normalize('α β γ δ ε');
        $this->assertStringContainsString('a b g d e', $result);
    }

    // ============================================================
    // TESTS DE REMOVE SPECIAL CHARS AVEC \p{L} ET \p{N}
    // ============================================================

    public function test_remove_special_chars_keep_unicode_letters(): void
    {
        $result = $this->normalizer->removeSpecialChars('Müller café cœur');
        $this->assertMatchesRegularExpression('/Müller\s+café\s+cœur/', $result);
    }

    public function test_remove_special_chars_keep_numbers(): void
    {
        $result = $this->normalizer->removeSpecialChars('Test123 !@#$%^&*()_+');
        $this->assertStringContainsString('Test123', $result);
        $this->assertStringNotContainsString('!@#$%^&*()_+', $result);
    }

    // ============================================================
    // TESTS DE NORMALISATION COMPLÈTE AVEC CARACTÈRES MULTILINGUES
    // ============================================================

    public function test_normalize_multilingual_text(): void
    {
        $text = 'Bonjour! Hello! Hola! Привет! こんにちは!';
        $result = $this->normalizer->normalize($text);
        $this->assertStringContainsString('bonjour hello hola privet', $result);
        $this->assertStringNotContainsString('konnichiwa', $result);
    }

    public function test_extract_words_from_multilingual_text(): void
    {
        $text = 'Bonjour! Hello! Hola! Привет! こんにちは!';
        $result = $this->normalizer->extractWords($text);
        $this->assertContains('bonjour', $result);
        $this->assertContains('hello', $result);
        $this->assertContains('hola', $result);
        $this->assertContains('privet', $result);
        $this->assertNotContains('konnichiwa', $result);
    }
}
