<script>
(() => {
  // jika project kamu berada di subfolder (index.php in URL), set basePrefix sesuai
  // contoh untuk env dev mu: '/deftrack/public/index.php'
  const apiPrefix = (() => {
    // try to detect base automatically:
    const base = window.location.pathname.split('/index.php')[0] || '';
    return base + '/index.php';
  })();

  // helper fetch categories for departmentId -> returns array
  async function fetchCategoriesForDepartment(deptId) {
    if (!deptId) return [];
    try {
      const url = `${apiPrefix}/api/departments/${encodeURIComponent(deptId)}/categories`;
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) {
        console.warn('fetch categories failed', res.status);
        return [];
      }
      const j = await res.json();
      return Array.isArray(j.data) ? j.data : [];
    } catch (e) {
      console.error('fetchCategoriesForDepartment error', e);
      return [];
    }
  }

  // render options html from category array
  function renderCategoryOptions(categories, includePlaceholder = true) {
    const placeholder = '<option value="">— Pilih Kategori —</option>';
    const opts = categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    return (includePlaceholder ? placeholder : '') + opts;
  }

  // small escape for safety
  function escapeHtml(str) {
    if (str == null) return '';
    return String(str).replace(/[&<>"'`=\/]/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'})[s]);
  }

  // fill all .category selects on page with categories list
  function fillAllCategorySelects(categories) {
    const html = renderCategoryOptions(categories);
    document.querySelectorAll('.category').forEach(sel => {
      sel.innerHTML = html;
    });
  }

  // fill a single row's category select (rowEl can be row container or element)
  function fillCategoriesOnRow(rowEl, categories) {
    if (!rowEl) return;
    const sel = rowEl.querySelector('.category');
    if (!sel) return;
    sel.innerHTML = renderCategoryOptions(categories);
  }

  // when department changes: fetch categories and apply to all rows
  async function onDepartmentChange(ev) {
    const depSel = ev.target || document.getElementById('departmentSelect');
    const deptId = depSel ? depSel.value : null;
    if (!deptId) {
      // clear selects to placeholder
      document.querySelectorAll('.category').forEach(s => s.innerHTML = '<option value="">— Pilih Kategori —</option>');
      return;
    }
    const cats = await fetchCategoriesForDepartment(deptId);
    fillAllCategorySelects(cats);
    // also ensure existing rows (in case some were added after initial fill)
    document.querySelectorAll('#lines .line-row').forEach(row => fillCategoriesOnRow(row, cats));
  }

  // wire department select event
  const depEl = document.getElementById('departmentSelect');
  if (depEl) {
    depEl.addEventListener('change', onDepartmentChange);
  }

  // call once on load if a department already selected
  document.addEventListener('DOMContentLoaded', async function() {
    const dep = document.getElementById('departmentSelect');
    if (dep && dep.value) {
      // populate categories initially
      await onDepartmentChange({ target: dep });
    }
    // ensure that when rows are dynamically added we populate categories for the new row:
    // observe #lines for added nodes
    const linesContainer = document.getElementById('lines');
    if (linesContainer) {
      const obs = new MutationObserver(async function(muts) {
        // on add nodes, get current categories for selected dept and fill the new row(s)
        const deptId = dep ? dep.value : null;
        const cats = deptId ? await fetchCategoriesForDepartment(deptId) : [];
        for (const m of muts) {
          for (const n of m.addedNodes) {
            if (n.nodeType === 1 && n.classList.contains('line-row')) {
              fillCategoriesOnRow(n, cats);
            }
          }
        }
      });
      obs.observe(linesContainer, { childList: true });
    }
  });

  // If your addNewLineRow clones an existing row and resets dataset/wired flag,
  // ensure it does NOT copy <select> options; our MutationObserver will fill it.
  // If you want immediate fill from addNewLineRow, call fillCategoriesOnRow(newRow, currentCats).
})();
</script>
