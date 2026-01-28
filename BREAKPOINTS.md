# Modal breakpoints – do not hardcode

All responsive modal behavior on the front end (mobile layout, **mobile width**, smaller text, stacked CTA, and “force image to top”) must use the **single** value from **Styling → Mobile breakpoint** (`styling.mobile_layout_breakpoint`). Do **not** hardcode a pixel value (e.g. 768px) in CSS or JS for this behavior.

- **Backend:** `ModalService` normalizes styling so `mobile_layout_breakpoint` is always a non-empty string when sent to the front end (default `768px` when the field is empty). The front end always receives a value.
- **Front end:** JS sets `data-mobile-layout-breakpoint` on the overlay and uses it to toggle `.modal-system--mobile-layout` and `.modal-system--force-top-active` at the chosen width. No `@media (max-width: …)` in `modal-system.css` for this; breakpoint is driven by the class.
- **New behavior:** If you add new “mobile” behavior, use the same breakpoint: read from `styling.mobile_layout_breakpoint` / `data-mobile-layout-breakpoint` and toggle a class in JS; do not add a new hardcoded media query.
