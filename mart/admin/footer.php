</main>

<footer class="admin-footer">
  <div>&copy; <?= date('Y') ?> Dynamic Mart Admin</div>
  <div>
    Logged in as <strong><?= htmlspecialchars($_SESSION['admin_email'] ?? '') ?></strong>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleAdminSidebar(){
  document.getElementById('adminSidebar').classList.toggle('open');
  document.getElementById('sidebarBackdrop').classList.toggle('open');
}

/* Auto-dismiss alerts */
document.querySelectorAll('.alert').forEach(function(a){
  setTimeout(function(){
    a.style.transition='opacity .4s';
    a.style.opacity='0';
    setTimeout(function(){a.remove();},400);
  },3000);
});

/* Close sidebar on link click (mobile) */
document.querySelectorAll('.admin-sidebar a').forEach(function(l){
  l.addEventListener('click', function(){
    if (window.innerWidth < 992) {
      document.getElementById('adminSidebar').classList.remove('open');
      document.getElementById('sidebarBackdrop').classList.remove('open');
    }
  });
});
</script>

<style>
.admin-footer{
  margin-left:240px;
  padding:16px 24px;
  background:#fff;
  border-top:1px solid #e3e6ee;
  color:#8a93a6;
  font-size:.8rem;
  display:flex;
  justify-content:space-between;
  align-items:center;
  flex-wrap:wrap;
  gap:8px;
}
@media (max-width:992px){
  .admin-footer{margin-left:0;}
}
</style>
</body>
</html>