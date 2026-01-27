# Prompt Learnings & Implementation Notes

This document captures learnings from building and refining the Drupal Modal System admin UI and features. Use it as context for future prompts or handoffs when working on layout, form structure, or responsive behavior.

---

## 1. Responsive “Collapse Sooner”

**Goal:** Columns and panels should switch to fewer columns / stacked layout at *larger* viewport widths than default.

**Interpretation:** “Collapse sooner” means the *compact* layout (2-col, 1-col, stacked) should apply at *higher* viewport widths. So we *raise* breakpoints:

- **Six Visual Effects fields** (Grayscale, Opacity, Brightness, Saturation, etc.): Use 2 columns until 1200px; 3 columns only from 1200px up (not from 1024px).
- **Overlay Gradient + Preview panels:** Stack (full-width, vertical) below 1200px; side-by-side 50/50 only at 1200px+.

**Implementation:** Introduce a `min-width: 1200px` block for the “wide” layout (3-col, 50/50 panels). Use `max-width: 1199px` for the stacking/compact rules so they win at smaller widths.

---

## 2. Flex Stacking: Container Must Be a Flex Container

**Symptom:** Overlay Gradient and Preview stay side-by-side even when a “stack” media query runs; it “looks like they’re not in a container.”

**Cause:** The stacking rule only set `flex-direction: column` and child widths on `.modal-image-effects-panels`. It never set `display: flex` on that element. Below 768px, neither the 1024px nor the 768px block runs, so the panels container never became a flex container. `flex-direction` has no effect without `display: flex`.

**Fix:** In the stacking media block (`max-width: 1199px`), style the *same* wrapper that the wide layout uses (e.g. `.modal-image-effects .details-wrapper > .modal-image-effects-panels`) and set:

- `display: flex; flex-direction: column; width: 100%;` (and full-width flex basis) on the container
- `width: 100%; max-width: 100%` (and flex as needed) on `.modal-image-effects-panels > *`

**Learning:** Stacking rules must explicitly make the parent a flex container in that same block. Child width/flex rules alone are not enough if the parent never gets `display: flex`.

---

## 3. Use the Same Selector for Stack and Wide Layout

**Issue:** When viewport is &lt; 768px, the 1024px and 768px media blocks don’t apply. If the “stack” rule targets only `.modal-image-effects-panels` and not the full “details-wrapper &gt; panels” selector, the container may never get `display: flex` or full width from our CSS.

**Learning:** Define the stacking layout on the *same* selector used for the wide layout (e.g. `.modal-image-effects .details-wrapper > .modal-image-effects-panels` and the Claro variant). That way the stack rule applies whenever the viewport is in range, regardless of which other breakpoint blocks run.

---

## 4. Drupal Form Structure: Visual Effects

**Structure:**

- **Effects** is a `#type => 'details'` with class `modal-image-effects`. Its content is rendered inside a wrapper (Claro: `details-wrapper` / `claro-details__wrapper`).
- **Direct children of that wrapper:** six `.form-item` elements (background_color, blend_mode, grayscale, opacity, brightness, saturation) and one **effects_panels** container.
- **effects_panels** (`modal-image-effects-panels`) is a `#type => 'container'` whose direct children are **overlay_gradient** (fieldset) and **preview** (fieldset/container).

**CSS:**

- Target the six small fields with: `.modal-image-effects .details-wrapper > .form-item` (and Claro equivalent).
- Target the panels row with: `.modal-image-effects .details-wrapper > .modal-image-effects-panels`.
- Target the two panels with: `.modal-image-effects-panels > *`.

**Learning:** Overlay Gradient and Preview live inside their own flex container (`effects_panels`), separate from the six effect fields. Only that container’s children (the two panels) change at the breakpoint; the six fields are laid out via the parent details-wrapper flex/grid.

---

## 5. Slider + Value in One Form Cell (Layout & Colors)

**Goal:** Show a range slider and its current value (e.g. “1.5x”, “50%”) in one grid cell, with the value under the slider.

**Approach:**

- Wrap the slider in a **container** (e.g. `confetti_size_group`, `overlay_opacity_group`) with a class like `modal-slider-with-display`.
- Use `#prefix` / `#suffix` to wrap the slider in a row div (e.g. `<div class="modal-slider-row">`).
- Put the human-readable value in **`#field_suffix`** so it is rendered inside the same form element (under the slider) and stays in the same grid cell.
- Use a `<span id="...">` for the value and JS (e.g. in `modal-form-persistence.js`) to update it from the slider’s `input` event.

**Learning:** `#field_suffix` keeps the value in the same form row/cell as the slider. Use a wrapper container and `#states` when the control is conditional (e.g. confetti size only when “Confetti” is selected).

---

## 6. Claro / Details Wrapper Class Names

**Issue:** Admin layout CSS may assume a single wrapper class. Claro (and other themes) can use different classes for the content area of a `details` element.

**Learning:** Target both common patterns in admin CSS, e.g.:

- `.modal-image-effects .details-wrapper`
- `.modal-image-effects .claro-details__wrapper`

Use the same rules for both so layout works regardless of which class the theme outputs.

---

## 7. Typography and Layout & Colors Admin Layout

**Typography:**

- Headline and Subheadline typography are in separate fieldsets, side-by-side on wider screens.
- Use a wrapper container (e.g. `modal-typography-container`) and give each fieldset a flex basis with a `min-width` (e.g. 280px) so they don’t collapse too far. Use a 2-column grid on the *inner* wrapper of each fieldset for the form items.

**Layout & Colors:**

- Limit width with `max-width: 800px` and a 2-column grid on the fieldset wrapper.
- Confetti Size and Overlay Opacity use the slider+value pattern above so each control and its value stay in one cell.

---

## 8. Confetti and Decorative Effects

**Features:**

- **Decorative effect** options: None, Confetti, Streamers (and possible future options).
- **Confetti:** Uses confetti.js; particle size is configurable (stored as 50–200, displayed as 0.5x–2.0x). Shown when modal opens.
- **Overlay opacity:** Slider (0–100%) for the page overlay. Lower values make it easier to see decorative effects (e.g. confetti) behind/through the overlay.

**Form:** Confetti size lives under Layout & Colors, visibility tied to “Confetti” via `#states`. Overlay opacity is always visible there. Both use the slider+value pattern.

---

## Summary Table: Key Breakpoints (admin.css)

| Viewport        | Six effect fields | Overlay Gradient + Preview |
|-----------------|-------------------|----------------------------|
| &lt; 768px      | 2-col             | Stacked                    |
| 768px–1199px    | 2-col             | Stacked                    |
| ≥ 1200px        | 3-col             | 50/50 side-by-side         |

---

*Last updated from implementation work on Layout & Colors, Visual Effects responsive layout, confetti/decorative effects, and admin stacking behavior.*
