(() => {
  'use strict';
  const menu = document.getElementById('help-navigation');
  const desktop = window.matchMedia('(min-width: 960px)');
  const sync = () => { menu.open = desktop.matches; };
  sync();
  desktop.addEventListener('change', sync);
})();
