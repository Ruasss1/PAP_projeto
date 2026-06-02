(() => {
  const applyTheme = () => {
    const theme = localStorage.getItem('pap-theme') || 'dark';
    document.documentElement.setAttribute('data-theme', theme);
  };

  const initMasterUi = () => {
    if (!document.body) return;
    document.body.classList.add('ui-master');

    // animação suave em cards principais
    const selectors = '.card, .panel, .widget, .stats-card, .table-container';
    const nodes = document.querySelectorAll(selectors);
    nodes.forEach((el, i) => {
      if (i < 14) {
        el.classList.add('ui-fade-up');
        el.style.animationDelay = `${Math.min(i * 35, 280)}ms`;
      }
    });
  };

  applyTheme();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMasterUi, { once: true });
  } else {
    initMasterUi();
  }

  window.PAP_MASTER_UI = {
    refresh() {
      initMasterUi();
    }
  };
})();
