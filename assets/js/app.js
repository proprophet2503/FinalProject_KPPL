'use strict';

/**
 * Format & batasi input numerik di sisi klien.
 * - [data-rupiah] : hanya angka, tampil dengan titik ribuan (1.000.000).
 * - [data-int]    : hanya angka (tanpa pemisah).
 * Validasi otoritatif tetap di server (lib.php).
 */
(function () {
    /** Buang semua karakter selain digit. */
    function digitsOnly(s) {
        return (s || '').replace(/\D+/g, '');
    }

    /** 1000000 -> "1.000.000" */
    function groupThousands(digits) {
        if (!digits) return '';
        return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function attachRupiah(input) {
        function reformat() {
            var caretFromEnd = input.value.length - input.selectionStart;
            input.value = groupThousands(digitsOnly(input.value));
            // Pulihkan posisi kursor sebisanya.
            var pos = input.value.length - caretFromEnd;
            try { input.setSelectionRange(pos, pos); } catch (e) { /* ignore */ }
        }
        input.addEventListener('input', reformat);
        reformat(); // format nilai awal dari server
    }

    function attachInt(input) {
        input.addEventListener('input', function () {
            input.value = digitsOnly(input.value);
        });
        input.value = digitsOnly(input.value);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-rupiah]').forEach(attachRupiah);
        document.querySelectorAll('[data-int]').forEach(attachInt);
    });
})();
