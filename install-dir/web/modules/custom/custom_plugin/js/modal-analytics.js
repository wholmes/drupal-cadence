/**
 * @file
 * JavaScript for Modal Analytics page - Chart.js visualizations.
 */

(function (Drupal, $) {
  'use strict';

  Drupal.behaviors.modalAnalytics = {
    attach: function (context, settings) {
      // Only run once per page load.
      if (context !== document) {
        return;
      }

      const analyticsData = settings.modalAnalytics?.analyticsData || {};
      const modalIds = Object.keys(analyticsData);

      // Check if we have data.
      if (modalIds.length === 0) {
        // Show empty state message.
        const chartsContainer = document.querySelector('.modal-analytics-charts');
        if (chartsContainer) {
          const emptyMessage = document.createElement('p');
          emptyMessage.className = 'modal-analytics-empty';
          emptyMessage.textContent = Drupal.t('No analytics data available for visualization.');
          chartsContainer.appendChild(emptyMessage);
        }
        return;
      }

      // Prepare data arrays for charts.
      const labels = [];
      const impressions = [];
      const conversionRates = [];
      const cta1Clicks = [];
      const cta2Clicks = [];
      const dismissals = [];

      modalIds.forEach((modalId) => {
        const data = analyticsData[modalId];
        labels.push(data.modal.label || data.modal.id);
        impressions.push(data.impressions || 0);
        conversionRates.push(data.conversion_rate || 0);
        cta1Clicks.push(data.cta1_clicks || 0);
        cta2Clicks.push(data.cta2_clicks || 0);
        dismissals.push(data.dismissals || 0);
      });

      // Chart.js color palette.
      const colors = {
        primary: '#0073aa',
        success: '#28a745',
        warning: '#ffc107',
        danger: '#dc3545',
        info: '#17a2b8',
        secondary: '#6c757d',
      };

      // Create impressions chart.
      const impressionsCanvas = document.getElementById('impressions-chart');
      if (impressionsCanvas) {
        const ctx = impressionsCanvas.getContext('2d');
        new Chart(ctx, {
          type: 'bar',
          data: {
            labels: labels,
            datasets: [{
              label: Drupal.t('Impressions'),
              data: impressions,
              backgroundColor: colors.primary,
              borderColor: colors.primary,
              borderWidth: 1
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
              legend: {
                display: false
              },
              tooltip: {
                callbacks: {
                  label: function(context) {
                    return Drupal.t('Impressions: @count', { '@count': context.parsed.y });
                  }
                }
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                ticks: {
                  precision: 0
                }
              }
            }
          }
        });
      }

      // Create conversion rate chart.
      const conversionCanvas = document.getElementById('conversion-chart');
      if (conversionCanvas) {
        const ctx = conversionCanvas.getContext('2d');
        
        // Color bars based on conversion rate.
        const backgroundColors = conversionRates.map(rate => {
          if (rate >= 5) return colors.success;
          if (rate >= 2) return colors.warning;
          return colors.danger;
        });

        new Chart(ctx, {
          type: 'bar',
          data: {
            labels: labels,
            datasets: [{
              label: Drupal.t('Conversion Rate (%)'),
              data: conversionRates,
              backgroundColor: backgroundColors,
              borderColor: backgroundColors,
              borderWidth: 1
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
              legend: {
                display: false
              },
              tooltip: {
                callbacks: {
                  label: function(context) {
                    return Drupal.t('Conversion Rate: @rate%', { '@rate': context.parsed.y.toFixed(2) });
                  }
                }
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                  callback: function(value) {
                    return value + '%';
                  }
                }
              }
            }
          }
        });
      }

      // Optional: Create CTA comparison chart if we have a third canvas.
      const ctaCanvas = document.getElementById('cta-comparison-chart');
      if (ctaCanvas) {
        const ctx = ctaCanvas.getContext('2d');
        new Chart(ctx, {
          type: 'bar',
          data: {
            labels: labels,
            datasets: [
              {
                label: Drupal.t('CTA 1 Clicks'),
                data: cta1Clicks,
                backgroundColor: colors.info,
                borderColor: colors.info,
                borderWidth: 1
              },
              {
                label: Drupal.t('CTA 2 Clicks'),
                data: cta2Clicks,
                backgroundColor: colors.secondary,
                borderColor: colors.secondary,
                borderWidth: 1
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
              legend: {
                display: true,
                position: 'top'
              },
              tooltip: {
                callbacks: {
                  label: function(context) {
                    return context.dataset.label + ': ' + context.parsed.y;
                  }
                }
              }
            },
            scales: {
              x: {
                stacked: false
              },
              y: {
                beginAtZero: true,
                ticks: {
                  precision: 0
                }
              }
            }
          }
        });
      }
    }
  };

})(Drupal, jQuery);
