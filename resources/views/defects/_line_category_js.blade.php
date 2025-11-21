<script>
document.getElementById('departmentSelect').addEventListener('change', async function(){
const deptId = this.value;
if (!deptId) return;
try {
const res = await fetch(`/deftrack/public/index.php/api/departments/${deptId}/categories`);
const j = await res.json();
const opts = j?.data?.map(c => `<option value="${c.id}">${c.name}</option>`).join('') || '';
document.querySelectorAll('.category').forEach(sel => {
sel.innerHTML = '<option value="">— Pilih Kategori —</option>' + opts;
});
} catch(e){ console.error(e); }
});
</script>