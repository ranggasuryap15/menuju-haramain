<?php

/**
 * File: database/migrations/2026_10_04_000001_modify_identity_number_to_text_in_family_members_table.php
 * Tujuan: Mengubah tipe kolom identity_number menjadi TEXT dan mengenkripsi NIK eksisting sesuai UU PDP No. 27 Tahun 2022
 * Dipakai Oleh: Artisan migrate (Database Migration)
 * Dependensi Utama: Illuminate\Database\Migrations\Migration, Schema, Crypt, DB
 * Daftar Fungsi Utama: up(), down()
 * Side Effect: Memodifikasi tipe kolom tabel family_members dan mengenkripsi data plaintext NIK eksisting
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->text('identity_number')->nullable()->change()->comment('NIK/Paspor terenkripsi (UU PDP)');
        });

        // Enkripsi data NIK plaintext yang mungkin sudah ada di database
        $members = DB::table('family_members')->whereNotNull('identity_number')->get();
        foreach ($members as $member) {
            $val = $member->identity_number;
            if ($val !== '' && $val !== null) {
                // Cek apakah data sudah terenkripsi atau masih plaintext
                try {
                    Crypt::decryptString($val);
                    // Jika decrypt berhasil, berarti sudah terenkripsi, biarkan
                } catch (\Throwable $e) {
                    // Jika decrypt gagal, berarti masih plaintext, enkripsi sekarang
                    DB::table('family_members')
                        ->where('id', $member->id)
                        ->update(['identity_number' => Crypt::encryptString($val)]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Dekripsi data sebelum mengembalikan kolom ke varchar
        $members = DB::table('family_members')->whereNotNull('identity_number')->get();
        foreach ($members as $member) {
            $val = $member->identity_number;
            if ($val !== '' && $val !== null) {
                try {
                    $decrypted = Crypt::decryptString($val);
                    DB::table('family_members')
                        ->where('id', $member->id)
                        ->update(['identity_number' => substr($decrypted, 0, 50)]);
                } catch (\Throwable $e) {
                    // Biarkan jika tidak dapat didekripsi
                }
            }
        }

        Schema::table('family_members', function (Blueprint $table) {
            $table->string('identity_number', 50)->nullable()->change()->comment('NIK atau Nomor Paspor');
        });
    }
};
