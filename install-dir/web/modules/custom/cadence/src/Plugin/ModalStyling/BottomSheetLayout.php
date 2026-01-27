<?php

namespace Drupal\cadence\Plugin\ModalStyling;

use Drupal\cadence\Plugin\ModalStyling\Attribute\ModalStylingAttribute;

/**
 * Bottom sheet modal layout (mobile-friendly).
 */
#[ModalStylingAttribute(
  id: 'bottom_sheet',
  label: 'Bottom Sheet',
  description: 'Modal slides up from the bottom (mobile-friendly)'
)]
class BottomSheetLayout extends ModalStylingBase {

  /**
   * {@inheritdoc}
   */
  public function buildCustomizationForm(array $form, array $customizations): array {
    $form['background_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Background Color'),
      '#default_value' => $customizations['background_color'] ?? '#ffffff',
    ];

    $form['text_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Text Color'),
      '#default_value' => $customizations['text_color'] ?? '#000000',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function render(array $content, array $customizations): array {
    $build = [
      '#theme' => 'modal_bottom_sheet',
      '#content' => $content,
      '#customizations' => $customizations,
      '#attached' => [
        'library' => ['cadence/modal.bottom_sheet'],
      ],
    ];

    // Add inline CSS for customizations.
    if (!empty($customizations)) {
      $css = $this->generateCustomCSS($customizations);
      $build['#attached']['html_head'][] = [
        [
          '#tag' => 'style',
          '#value' => $css,
        ],
        'modal_customizations',
      ];
    }

    return $build;
  }

  /**
   * Generates custom CSS from customizations.
   */
  protected function generateCustomCSS(array $customizations): string {
    $css = '.modal-system--bottom-sheet {';
    if (isset($customizations['background_color'])) {
      $css .= 'background-color: ' . $customizations['background_color'] . ';';
    }
    if (isset($customizations['text_color'])) {
      $css .= 'color: ' . $customizations['text_color'] . ';';
    }
    $css .= '}';

    return $css;
  }

}
