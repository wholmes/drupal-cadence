# Simple Background Image Carousel - Implementation Plan

## Overview
Add a simple, lightweight carousel feature that rotates through multiple background images with fade transitions. This is a simplified version of the existing slideshow - just fade, autoplay, and duration. No arrows, dots, or manual controls.

## Current State Analysis

### What Already Exists:
✅ **Multiple Image Support**: Code already handles `fids` array (multiple file IDs)
✅ **Background Images**: Existing slideshow uses `background-image` CSS property
✅ **Slideshow Infrastructure**: JavaScript has `buildSlideshow()` method
✅ **Data Structure**: Schema supports `fids` and `slideshow` configuration

### What Needs to Change:
❌ **Simplify**: Remove arrows, dots, manual controls
❌ **Fade Only**: Force fade transition (no slide/other transitions)
❌ **Autoplay Only**: Always autoplay, no pause controls
❌ **Simple Duration**: Just one duration setting (per image)
❌ **Form UI**: Add simple carousel toggle and duration field

---

## Implementation Plan

### Phase 1: Form Changes

#### 1.1 Enable Multiple Image Upload
**File**: `src/Form/ModalForm.php`

**Current**: Form uses `managed_file` which already supports multiple uploads via `#multiple => TRUE`

**Change Needed**:
- Enable multiple file selection in the image upload field
- Add checkbox: "Enable Image Carousel" (only shows when 2+ images uploaded)
- Add field: "Image Duration (seconds)" - simple number field, default 5 seconds

**UI Flow**:
1. User uploads multiple images (existing field, just enable multiple)
2. If 2+ images uploaded → show "Enable Image Carousel" checkbox
3. If carousel enabled → show "Image Duration" field
4. If carousel disabled OR only 1 image → use existing single image behavior

**Key Points**:
- Keep existing single image behavior 100% intact
- Carousel is opt-in (checkbox)
- Only activates when 2+ images present

---

#### 1.2 Save Carousel Settings
**File**: `src/Form/ModalForm.php` (save method)

**Data Structure to Save**:
```php
$image_array = [
  'fid' => $single_fid,           // Keep for backward compatibility
  'fids' => [$fid1, $fid2, ...],  // Multiple images
  'carousel_enabled' => TRUE/FALSE,
  'carousel_duration' => 5,        // Seconds per image
  // ... all existing fields (placement, mobile, effects, etc.)
];
```

**Key Points**:
- Save `fids` array when multiple images uploaded
- Save `carousel_enabled` boolean
- Save `carousel_duration` integer (seconds)
- Keep all existing image fields (placement, mobile, height, effects, etc.)

---

### Phase 2: Backend Data Processing

#### 2.1 Update ModalService
**File**: `src/ModalService.php`

**Current**: Already handles `fids` array and creates `urls` array

**Change Needed**:
- Pass carousel settings to frontend
- Include `carousel_enabled` and `carousel_duration` in image data

**Code Pattern**:
```php
if (!empty($urls)) {
  $content['image'] = [
    'url' => $urls[0],        // Backward compatibility
    'urls' => $urls,          // All image URLs
    'carousel_enabled' => !empty($image_data['carousel_enabled']),
    'carousel_duration' => (int) ($image_data['carousel_duration'] ?? 5),
    // ... all existing fields
  ];
}
```

**Key Points**:
- If `carousel_enabled` is false OR only 1 image → use single image code path
- If `carousel_enabled` is true AND 2+ images → use carousel code path

---

### Phase 3: Frontend JavaScript

#### 3.1 Simplify Slideshow Logic
**File**: `js/modal-system.js`

**Current**: `buildSlideshow()` creates complex slideshow with arrows, dots, multiple transitions

**Change Needed**: 
- Create new `buildSimpleCarousel()` method OR
- Modify existing `buildSlideshow()` to have "simple mode"

**Recommended**: Create separate method for clarity

**New Method Structure**:
```javascript
buildSimpleCarousel(urls, duration, placement, mobileForceTop, imageHeight, mobileBreakpoint, imageData) {
  // Create container (same as single image)
  // Apply all existing styling (placement, mobile, height, effects)
  // Create single div with background-image
  // Cycle through urls array with fade transitions
  // Autoplay only, no controls
}
```

**Key Points**:
- Use same container structure as single image
- Apply ALL existing styling (placement, mobile, height, effects, etc.)
- Single div that changes background-image
- CSS fade transition
- JavaScript handles timing/cycling

---

#### 3.2 Carousel Detection Logic
**File**: `js/modal-system.js` (in `showModal()` method)

**Current Logic**:
```javascript
if (imageUrls.length > 1 && imageData.slideshow) {
  // Build complex slideshow
} else {
  // Build single image
}
```

**New Logic**:
```javascript
if (imageUrls.length > 1 && imageData.carousel_enabled) {
  // Build simple carousel (fade only, autoplay)
} else if (imageUrls.length > 1 && imageData.slideshow) {
  // Build complex slideshow (existing, keep for backward compat)
} else {
  // Build single image (existing behavior)
}
```

**Key Points**:
- Carousel takes priority over slideshow if both enabled
- Single image path unchanged
- Backward compatibility maintained

---

#### 3.3 Simple Carousel Implementation
**File**: `js/modal-system.js`

**Method**: `buildSimpleCarousel()`

**Structure**:
1. Create container div (same as single image container)
2. Apply all existing attributes (placement, mobile, height, effects)
3. Set initial background-image to first URL
4. Store URLs array and duration in data attributes
5. Start autoplay timer
6. On timer: fade out → change image → fade in

**Fade Implementation**:
- Use CSS opacity transition
- JavaScript changes background-image during fade
- CSS handles smooth transition

**Code Pattern**:
```javascript
// Create container (same structure as single image)
const container = document.createElement('div');
container.className = 'modal-system--image-container modal-system--image-' + placement;
container.style.backgroundImage = 'url(' + urls[0] + ')';
// ... apply all existing styling (mobile, height, effects)

// Store carousel data
container.setAttribute('data-carousel-urls', JSON.stringify(urls));
container.setAttribute('data-carousel-duration', duration);
container.setAttribute('data-carousel-current', 0);

// Add fade class
container.classList.add('modal-system--carousel-fade');

// Start autoplay
this.startCarousel(container);
```

---

#### 3.4 Carousel Autoplay Logic
**File**: `js/modal-system.js`

**New Method**: `startCarousel(container)`

**Functionality**:
1. Get URLs array and duration from data attributes
2. Set interval timer (duration * 1000 milliseconds)
3. On interval:
   - Fade out current image (opacity 0)
   - Wait for transition
   - Change background-image to next URL
   - Fade in (opacity 1)
   - Update current index
   - Loop back to first image when at end

**Key Points**:
- Use `setInterval` for timing
- Use CSS transitions for smooth fade
- Clean up interval when modal closes
- Handle edge cases (1 image, empty array, etc.)

---

### Phase 4: CSS Styling

#### 4.1 Carousel Fade Transitions
**File**: `css/modal-system.css`

**Add**:
```css
/* Simple Carousel - Fade Transitions */
.modal-system--carousel-fade {
  transition: opacity 1s ease-in-out;
  position: relative;
}

.modal-system--carousel-fade.fade-out {
  opacity: 0;
}

.modal-system--carousel-fade.fade-in {
  opacity: 1;
}
```

**Key Points**:
- Smooth 1-second fade transition
- Opacity-based (not background-image swap)
- Works with all existing background-image styling

---

### Phase 5: Config Schema

#### 5.1 Update Schema
**File**: `config/schema/cadence.schema.yml`

**Add to image mapping**:
```yaml
carousel_enabled:
  type: boolean
  label: 'Carousel enabled'
carousel_duration:
  type: integer
  label: 'Carousel duration (seconds)'
```

**Key Points**:
- Keep existing `slideshow` schema (backward compatibility)
- Add new `carousel_*` fields
- Both can coexist (carousel takes priority)

---

## User Experience Flow

### Scenario 1: Single Image (No Change)
1. User uploads 1 image
2. Carousel checkbox hidden
3. Works exactly as before

### Scenario 2: Multiple Images, Carousel Disabled
1. User uploads 2+ images
2. Carousel checkbox appears
3. User leaves unchecked
4. Uses first image only (existing behavior)

### Scenario 3: Multiple Images, Carousel Enabled
1. User uploads 2+ images
2. Carousel checkbox appears
3. User checks "Enable Image Carousel"
4. Duration field appears (default: 5 seconds)
5. User sets duration (e.g., 3 seconds)
6. Save → Modal shows carousel with fade transitions

---

## Technical Considerations

### Background Image Behavior (100% Preserved)
✅ **Placement**: Top/Bottom/Left/Right - all work
✅ **Mobile Force Top**: Still works
✅ **Mobile Breakpoint**: Still works
✅ **Mobile Height**: Still works
✅ **Mobile Image**: Still works (carousel only on desktop, mobile image on mobile)
✅ **Height Settings**: All height settings apply
✅ **Image Effects**: Background color, blend mode, grayscale - all work
✅ **Max Height**: Top/bottom max height still works

**Key**: Carousel is just cycling background-image URLs. All existing CSS and behavior applies.

---

### Edge Cases

1. **1 Image Uploaded**: Carousel disabled, use single image
2. **2+ Images, Carousel Disabled**: Use first image only
3. **Carousel Enabled, Then User Removes Images**: Fall back to single image
4. **Very Fast Duration (< 1 second)**: Minimum 1 second recommended
5. **Modal Closed During Carousel**: Clean up interval timer
6. **Multiple Modals with Carousels**: Each has independent timer

---

## Implementation Priority

### Must Have (MVP):
1. ✅ Multiple image upload enabled
2. ✅ Carousel checkbox in form
3. ✅ Duration field
4. ✅ Save carousel settings
5. ✅ Pass to frontend
6. ✅ Simple fade carousel JavaScript
7. ✅ CSS fade transitions
8. ✅ Autoplay with duration

### Nice to Have (Future):
- Pause on hover (optional)
- Loop control (always loop for now)
- Transition speed control (fixed 1s fade for now)

---

## Backward Compatibility

### Existing Behavior Preserved:
- ✅ Single image: No change
- ✅ Multiple images without carousel: Uses first image (existing)
- ✅ Existing slideshow: Still works if `slideshow` config exists
- ✅ All image settings: Work identically

### Migration Path:
- Existing modals with `fids` but no carousel → use first image
- Existing modals with `slideshow` → keep using slideshow
- New modals → can choose carousel or slideshow

---

## Testing Checklist

- [ ] Upload 1 image → no carousel option, works as before
- [ ] Upload 2+ images → carousel checkbox appears
- [ ] Enable carousel → duration field appears
- [ ] Set duration → saves correctly
- [ ] Carousel fades between images
- [ ] Autoplay works
- [ ] Loops continuously
- [ ] All placement options work (top/bottom/left/right)
- [ ] Mobile force top works
- [ ] Mobile height works
- [ ] Image effects work (background color, blend, grayscale)
- [ ] Height settings work
- [ ] Max height works
- [ ] Modal close cleans up timer
- [ ] Multiple modals with carousels work independently

---

## Code Organization

### Files to Modify:
1. `src/Form/ModalForm.php` - Form fields, save logic
2. `src/ModalService.php` - Pass carousel data to frontend
3. `js/modal-system.js` - Carousel detection, build method, autoplay
4. `css/modal-system.css` - Fade transition styles
5. `config/schema/cadence.schema.yml` - Schema updates

### New Methods:
- `buildSimpleCarousel()` - Creates carousel container
- `startCarousel()` - Handles autoplay logic
- `stopCarousel()` - Cleans up on modal close

---

## Pain Points & Solutions

### Pain Point 1: Background Image Swap Timing
**Problem**: Changing background-image during fade can cause flash
**Solution**: 
- Change image when opacity is 0 (invisible)
- Use `transitionend` event to ensure fade completes
- Or use two overlapping divs (current/next) and swap

### Pain Point 2: Timer Cleanup
**Problem**: Interval continues after modal closes
**Solution**:
- Store interval ID on container
- Clear on modal close
- Use `cleanupCarousel()` method

### Pain Point 3: Form State for Multiple Files
**Problem**: `managed_file` with multiple returns array format
**Solution**:
- Handle both `['fid'][0]` and `['fid']['fids']` formats
- Already handled in existing code, reuse pattern

### Pain Point 4: Carousel vs Slideshow Conflict
**Problem**: Both features might conflict
**Solution**:
- Carousel takes priority if `carousel_enabled` is true
- Slideshow only if carousel disabled AND slideshow config exists
- Clear priority order in detection logic

---

## Alternative: Overlay Approach

If background-image swap causes issues, consider:

**Two Overlapping Divs**:
- Current image div (opacity 1)
- Next image div (opacity 0, behind)
- Fade current to 0, fade next to 1
- Swap z-index, reset

**Pros**: Smoother transitions
**Cons**: More complex, two elements

**Recommendation**: Try simple approach first (single div), upgrade if needed.

---

## Success Criteria

✅ **Simple**: Just fade, autoplay, duration - nothing more
✅ **Preserved**: All existing background image behavior works
✅ **Opt-in**: Carousel is optional, doesn't break existing modals
✅ **Clean**: No arrows, dots, or manual controls
✅ **Smooth**: Fade transitions are smooth and professional

---

**Last Updated**: 2026-01-XX
**Status**: Planning Document - Ready for Implementation
**Complexity**: Medium (reuses existing infrastructure)
