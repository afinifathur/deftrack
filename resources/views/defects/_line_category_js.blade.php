<script>
// FILE: resources/views/defects/_line_category_js.blade.php
// Dropdown kategori per departemen (dipakai di defects/create).

(function () {
    'use strict';

    // Cache kategori untuk departemen aktif
    let currentDeptId = null;
    let currentCategoryOptions = '<option value="">— Pilih Kategori —</option>';

    // Base URL dari Laravel, dinormalisasi supaya tidak dobel /index.php
    const APP_BASE_URL = @json(url('/'));

    /**
     * Escape HTML sederhana untuk nama kategori.
     */
    function escapeHtml(s) {
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, function (m) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[m];
        });
    }

    /**
     * Bangun URL API kategori berdasarkan deptId.
     * Hasil: {base}/index.php/api/departments/{deptId}/categories
     * tanpa duplikasi index.php.
     */
    function buildCategoriesUrl(deptId) {
        if (!deptId) return null;

        let base = String(APP_BASE_URL || '').trim();

        // Hapus trailing slash
        base = base.replace(/\/+$/, '');

        // Hapus trailing /index.php (kalau ada)
        base = base.replace(/\/index\.php$/i, '');

        const baseWithIndex = base + '/index.php';

        return baseWithIndex +
            '/api/departments/' +
            encodeURIComponent(deptId) +
            '/categories';
    }

    /**
     * Ambil list kategori dari backend untuk departemen tertentu.
     * Sudah:
     *  - normalisasi base url (anti dobel index.php)
     *  - kirim cookie/session (credentials: 'same-origin')
     *  - cek content-type harus JSON
     */
    async function fetchCategoriesForDept(deptId) {
        if (!deptId) return [];

        const url = buildCategoriesUrl(deptId);
        if (!url) {
            console.warn('[categories] URL tidak dapat dibentuk, deptId kosong');
            return [];
        }

        console.debug('[categories] fetching url:', url);

        try {
            const res = await fetch(url, {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                // penting: kirim cookie/session supaya Laravel mengenali user
                credentials: 'same-origin' // pakai 'include' kalau lintas subdomain
            });

            const contentType = res.headers.get('content-type') || '';

            if (!res.ok) {
                const preview = await res.text();
                console.warn(
                    '[categories] HTTP error',
                    res.status,
                    res.statusText,
                    'preview:',
                    preview.substring(0, 300)
                );
                return [];
            }

            // Kalau backend balikin HTML (misalnya halaman login) jangan di-parse JSON
            if (!contentType.includes('application/json')) {
                const text = await res.text();
                console.warn(
                    '[categories] expected JSON, got',
                    contentType,
                    'status',
                    res.status,
                    'preview:',
                    text.substring(0, 300)
                );
                return [];
            }

            const j = await res.json();
            console.debug('[categories] response data', j);

            return Array.isArray(j.data) ? j.data : [];
        } catch (e) {
            console.error('[categories] fetch error', e);
            return [];
        }
    }

    /**
     * Build HTML <option> dari array kategori.
     */
    function buildOptionsHtml(categories) {
        const parts = ['<option value="">— Pilih Kategori —</option>'];

        categories.forEach(c => {
            parts.push(
                `<option value="${c.id}">${escapeHtml(c.name)}</option>`
            );
        });

        return parts.join('\n');
    }

    /**
     * Set isi semua <select.category> di halaman.
     */
    function setAllCategorySelects(optionsHtml) {
        document.querySelectorAll('.category').forEach(sel => {
            const prevVal = sel.value || '';
            sel.innerHTML = optionsHtml;

            if (prevVal !== '') {
                try {
                    sel.value = prevVal;
                } catch (_) {
                    // abaikan kalau value lama tidak ada di list baru
                }
            }
        });
    }

    /**
     * Set isi <select.category> untuk satu row (dipakai saat clone).
     */
    function setCategoryForRow(rowEl, optionsHtml) {
        const sel = rowEl.querySelector('.category');
        if (!sel) return;

        const prevVal = sel.value || '';
        sel.innerHTML = optionsHtml;

        if (prevVal !== '') {
            try {
                sel.value = prevVal;
            } catch (_) {
                // abaikan
            }
        }
    }

    /**
     * Helper global yang dipanggil dari create.blade.php
     * ketika menambah baris baru (clone).
     */
    window.__deftrack_setCategoryOptionsForRow = function (rowEl) {
        if (!rowEl) return;

        if (currentCategoryOptions && currentCategoryOptions.trim() !== '') {
            setCategoryForRow(rowEl, currentCategoryOptions);
            return;
        }

        const depSel = document.getElementById('departmentSelect');
        const deptId = depSel ? depSel.value : null;

        if (!deptId) {
            setCategoryForRow(rowEl, '<option value="">— Pilih Kategori —</option>');
            return;
        }

        fetchCategoriesForDept(deptId).then(list => {
            currentCategoryOptions = buildOptionsHtml(list);
            setCategoryForRow(rowEl, currentCategoryOptions);
        });
    };

    /**
     * Handler ketika departemen berubah.
     */
    async function onDepartmentChange(e) {
        const depSel = e && e.target
            ? e.target
            : document.getElementById('departmentSelect');

        if (!depSel) {
            console.warn('[categories] #departmentSelect not found');
            return;
        }

        const deptId = depSel.value;
        console.debug('[categories] department changed to', deptId);

        // Kalau departemen kosong, reset opsi kategori
        if (!deptId) {
            currentDeptId = null;
            currentCategoryOptions = '<option value="">— Pilih Kategori —</option>';
            setAllCategorySelects(currentCategoryOptions);
            return;
        }

        // Kalau sama dengan department sebelumnya, tidak usah refetch
        if (currentDeptId && String(currentDeptId) === String(deptId)) {
            console.debug('[categories] same dept, skip refetch');
            return;
        }

        currentDeptId = deptId;

        const cats = await fetchCategoriesForDept(deptId);
        currentCategoryOptions = buildOptionsHtml(cats);
        setAllCategorySelects(currentCategoryOptions);
    }

    /**
     * Init saat DOM siap.
     */
    document.addEventListener('DOMContentLoaded', function () {
        const depSel = document.getElementById('departmentSelect');

        if (!depSel) {
            console.warn('[categories] #departmentSelect not found on DOMContentLoaded');
            return;
        }

        // Pasang listener perubahan departemen
        depSel.addEventListener('change', onDepartmentChange);

        // Kalau form sudah punya departemen (old input / edit),
        // langsung load kategori awal
        if (depSel.value) {
            onDepartmentChange({ target: depSel });
        }
    });
})();
</script>
