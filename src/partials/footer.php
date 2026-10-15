      </main>
    </div><!-- /.app__main -->
  </div><!-- /.app -->

  <script>
    // Menu samping untuk layar kecil
    (function () {
      var toggle  = document.getElementById('menu-toggle');
      var sidebar = document.getElementById('sidebar');
      var overlay = document.getElementById('sidebar-overlay');
      if (!toggle || !sidebar || !overlay) return;

      function setOpen(open) {
        sidebar.classList.toggle('is-open', open);
        overlay.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
      }

      toggle.addEventListener('click', function () {
        setOpen(!sidebar.classList.contains('is-open'));
      });
      overlay.addEventListener('click', function () { setOpen(false); });
      document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') setOpen(false);
      });
    })();
  </script>
</body>
</html>
