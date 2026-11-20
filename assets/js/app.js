/* Shared front-end behaviour for the A&A ISDSS interface. */

// Shared chart defaults so every graph in the system looks the same.
if (window.Chart) {
  Chart.defaults.font.family = '"Segoe UI", system-ui, sans-serif';
  Chart.defaults.font.size = 12;
  Chart.defaults.color = '#7a726a';
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.legend.labels.boxWidth = 8;
}

window.AA = {
  // Sampled from the A&A Surplus badge: hinomaru red, sumi black, and
  // muted earth tones that sit beside them without fighting for attention.
  palette: ['#c41114', '#1f1d1c', '#b8860b', '#2b4c7e', '#2f7d4f',
            '#8e0a0d', '#8c7b6a', '#b5651d', '#4a6fa5', '#6d0508'],

  peso: function (v) {
    return '₱' + Number(v).toLocaleString('en-PH', {
      minimumFractionDigits: 0, maximumFractionDigits: 0
    });
  },

  /** Confirm before following a destructive link. */
  confirmLink: function (el, message) {
    return window.confirm(message || 'Are you sure you want to continue?');
  }
};

// Auto-submit filter forms when a select changes.
document.addEventListener('change', function (e) {
  if (e.target.matches('[data-autosubmit]')) {
    e.target.closest('form').submit();
  }
});

// Debounced live search boxes.
document.querySelectorAll('[data-live-search]').forEach(function (input) {
  let t;
  input.addEventListener('keyup', function (e) {
    if (e.key === 'Enter') { clearTimeout(t); return; }
    clearTimeout(t);
    t = setTimeout(function () { input.closest('form').submit(); }, 550);
  });
});

// Dismiss flash alerts automatically.
/* Keep feedback visible until explicitly dismissed.
setTimeout(function () {
  document.querySelectorAll('.alert-dismissible').forEach(function (a) {
    if (a.dataset.keep === undefined && window.bootstrap) {
      bootstrap.Alert.getOrCreateInstance(a).close();
    }
  });
}, 6000); */
