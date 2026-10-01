<?php
/**
 * File: tests/Feature/ExampleTest.php
 * Tujuan: Smoke test dasar redirect halaman utama ke login
 * Dipakai Oleh: PHPUnit / php artisan test
 * Dependensi Utama: Tests\TestCase
 * Daftar Fungsi Utama: test_root_redirects_to_login()
 * Side Effect: HTTP GET request ke /
 */

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Root url mengarahkan pengunjung ke halaman login.
     */
    public function test_root_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
