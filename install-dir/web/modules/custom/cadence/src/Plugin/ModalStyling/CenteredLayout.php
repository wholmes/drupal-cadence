<?php

namespace Drupal\cadence\Plugin\ModalStyling;

use Drupal\cadence\Plugin\ModalStyling\Attribute\ModalStylingAttribute;
use Drupal\Core\Form\FormStateInterface;

/**
 * Centered modal layout.
 */
#[ModalStylingAttribute(
  id: 'centered',
  label: 'Centered',
  description: 'Modal centered on the screen'
)]
class CenteredLayout extends ModalStylingBase {

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

    $form['overlay_opacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Overlay Opacity'),
      '#description' => $this->t('Background overlay opacity (0-1)'),
      '#default_value' => $customizations['overlay_opacity'] ?? 0.5,
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function render(array $content, array $customizations): array {
    $build = [
      '#theme' => 'modal_centered',
      '#content' => $content,
      '#customizations' => $customizations,
      '#attached' => [
        'library' => ['cadence/modal.centered'],
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
    $css = '.modal-system--centered {';
    if (isset($customizations['background_color'])) {
      $css .= 'background-color: ' . $customizations['background_color'] . ';';
    }
    if (isset($customizations['text_color'])) {
      $css .= 'color: ' . $customizations['text_color'] . ';';
    }
    $css .= '}';

    if (isset($customizations['overlay_opacity'])) {
      $css .= '.modal-system--overlay {';
      $css .= 'background-color: rgba(0, 0, 0, ' . $customizations['overlay_opacity'] . ');';
      $css .= '}';
    }

    return $css;
  }

}
