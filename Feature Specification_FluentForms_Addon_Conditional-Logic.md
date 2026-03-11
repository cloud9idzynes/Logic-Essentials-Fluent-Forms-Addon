## Feature specification for a Fluent Forms Free addon that adds field‑level conditional display logic

Below is a complete, prioritized feature list and implementation requirements you can use to write product requirements, acceptance tests, and developer tasks for an addon plugin that restores **show/hide field conditional logic** (and related missing behaviors) to the free Fluent Forms build.

---

### Summary (one sentence)

Add field‑level conditional display logic, a rule builder UI, runtime evaluation, admin controls, developer hooks, and safe fallbacks so form authors can show/hide fields and sections based on answers without upgrading to Pro.

---

## 1. Minimum Viable Product (MVP) — must‑have features

- **Field Visibility Rules**
  
  - **Show/Hide** — ability to mark any field (including Section/HTML/Container) as *conditionally visible*.
  - **Rule Types** — support basic operators: `is`, `is not`, `contains`, `does not contain`, `is empty`, `is not empty`, `greater than`, `less than`.
  - **Multiple Conditions** — allow multiple rules combined with **AND / OR**.
  - **Negation** — allow rule negation (NOT).
  - **Target Field Selector** — rules reference other fields by label or internal name; UI shows friendly labels.

- **Rule Builder UI (per field)**
  
  - **Toggle** to enable conditional logic in the field settings panel.
  - **Compact rule editor** with dropdowns: `[When] [Field] [Operator] [Value] [AND/OR] [Add rule]`.
  - **Preview** button to test rules in the editor (client‑side simulation).
  - **Validation** to prevent circular references (field A depends on B which depends on A).

- **Client‑side Runtime**
  
  - **Efficient evaluation** on page load and on change events for trigger fields.
  - **Debounced** evaluation for text inputs to avoid excessive reflows.
  - **Graceful fallback**: if JS disabled, all fields remain visible and submission still works.
  - **Animation option**: simple show/hide fade or slide (configurable).

- **Server‑side Enforcement**
  
  - On submission, server validates that hidden fields are either ignored or treated as empty (configurable).
  - Prevents spoofed submissions that include hidden fields by stripping or ignoring them server‑side.

- **Per‑form and Global Settings**
  
  - **Per‑form**: enable/disable conditional logic for that form.
  - **Global**: toggle to enable the addon globally; default ON after activation.

- **Accessibility**
  
  - When a field is hidden, remove it from the tab order and update `aria-hidden="true"`.
  - When shown, set focus management options (optional: focus first visible field).
  - Announce visibility changes to screen readers using `aria-live` region.

- **Compatibility**
  
  - Works with Fluent Forms’ existing field types (text, radio, checkbox, dropdown, date, file, HTML, section).
  - Works with Fluent Forms’ AJAX and non‑AJAX embeds, and with page builders (Elementor, WPBakery) where forms are embedded.

---

## 2. Important quality‑of‑life features (near‑term)

- **Rule Groups / Nested Logic**
  
  - Allow grouping rules into nested groups with their own AND/OR logic.

- **Field Value Normalization**
  
  - Normalize values for comparisons (trim, lowercase for `contains` unless case‑sensitive flag set).

- **Condition Types**
  
  - Add `starts with`, `ends with`, `matches regex`, `in list` (for multi‑select), `selected` (for checkboxes), `checked` (boolean).

- **Visibility for Containers / Columns**
  
  - Allow showing/hiding entire containers/rows/columns (if present) so multi‑field blocks can be toggled.

- **Conditional Required**
  
  - Make a field **required only when visible** (automatic) and optionally allow “required if” rules.

- **Conditional Default Values**
  
  - Set a default value for a field when it becomes visible.

- **Conditional Calculations**
  
  - Trigger recalculation of calculated fields when dependent fields change.

---

## 3. Advanced features / roadmap (post‑MVP)

- **Multi‑step branching**
  
  - Show/hide entire pages or steps in multi‑page forms based on rules.

- **Delayed/Timed Conditions**
  
  - Show a field after X seconds or after user spends Y time on page.

- **Complex event triggers**
  
  - Trigger on custom events (e.g., file uploaded, external API response).

- **Rule import/export**
  
  - Export rules as JSON and import into other forms.

- **Admin audit log**
  
  - Track rule changes with user, timestamp, and diff.

- **Visual flow builder**
  
  - Drag‑and‑drop visual flow for complex branching (optional).

---

## 4. Data model and storage

- **Schema**
  
  - Store rules in form meta as structured JSON:
    - `rules: [{ id, target_field, conditions: [{field, operator, value}], logic: "AND"|"OR", action: "show"|"hide", priority, enabled }]`
  - Version the rules (`schema_version`) for future migrations.

- **Indexing**
  
  - Keep a lightweight map of dependencies for quick client initialization:
    - `dependency_map: { trigger_field_id: [dependent_field_id, ...] }`

- **Migration**
  
  - Provide migration routine for future schema changes and for Pro → Free compatibility.

---

## 5. Client architecture and performance

- **Initialization**
  
  - On form render, load only the rule set for that form.
  - Build `dependency_map` and attach listeners only to trigger fields.

- **Event handling**
  
  - Use delegated listeners where possible.
  - Debounce text input events (e.g., 300ms).

- **Evaluation**
  
  - Evaluate only dependent fields when a trigger changes.
  - Short‑circuit evaluation for AND/OR groups.

- **Bundle**
  
  - Ship a small JS bundle (ES5 transpiled) and enqueue only on pages where the form is present.

---

## 6. Server‑side validation and security

- **Submission sanitization**
  
  - If a field was hidden at submission time, either:
    - Strip its value from the entry, or
    - Mark it as `hidden_at_submission: true` and ignore for processing (configurable).

- **Tamper protection**
  
  - Validate rule evaluation server‑side for critical flows (e.g., gated payments) by re‑evaluating rules using submitted values.

- **Nonce and capability checks**
  
  - Only allow users with `manage_options` or `edit_forms` capability to edit rules.

---

## 7. Admin UI and UX details

- **Where to place controls**
  
  - Add a **Conditional Logic** panel inside the existing field settings sidebar (same place Pro shows it).
  - Add a **Rules** tab in the form editor top bar for a global view of all rules.

- **UI elements**
  
  - Field dropdown shows `Label (field_name)` and search.
  - Operator dropdown with contextual help tooltips.
  - Value input adapts to field type (text, number, date, select list).
  - Friendly error messages for invalid rules (e.g., referencing non‑existent field).

- **Preview & Debug**
  
  - Live preview mode in the editor that simulates user answers and highlights which fields would show/hide.
  - Debug log showing last evaluation results for each rule.

- **Accessibility in UI**
  
  - Keyboard navigable rule builder, ARIA labels for controls.

---

## 8. Developer API and hooks

- **Filters / Actions**
  
  - `ff_addon_conditional_rules_before_save($form_id, $rules)` — validate/modify rules before save.
  - `ff_addon_conditional_rules_after_save($form_id, $rules)` — post‑save hook.
  - `ff_conditional_evaluate($form_id, $entry_values)` — server‑side evaluator; returns visible field list.
  - `ff_conditional_client_config($form_id)` — filter to modify client config JSON.

- **JS events**
  
  - `ff:rule:beforeEvaluate` — cancelable.
  - `ff:rule:afterEvaluate` — returns evaluation result.
  - `ff:field:visibilityChange` — payload `{ fieldId, visible }`.

- **REST endpoints**
  
  - `GET /ff-addon/v1/form/{id}/rules` — read rules (admin only).
  - `POST /ff-addon/v1/form/{id}/rules/preview` — evaluate rules with sample payload (admin only).

---

## 9. Testing and acceptance criteria

- **Unit tests**
  
  - Rule parser correctness for all operators.
  - Circular dependency detection.
  - Server‑side evaluator parity with client evaluator.

- **Integration tests**
  
  - Show/hide behavior across field types and containers.
  - Submission stripping/ignoring hidden fields.
  - Accessibility checks (aria attributes, tab order).

- **Performance tests**
  
  - Evaluate 100 rules on a form with 200 fields within acceptable time (target < 50ms on modern browsers).

- **Acceptance criteria (sample)**
  
  - Given a radio field with options A/B/C, a follow‑up field with rule `is A` must be hidden when B/C selected and visible when A selected.
  - Hidden fields must not be included in entry values unless configured to keep them.
  - Editor preview must reflect the same visibility as front‑end runtime.

---

## 10. UI copy, error messages, and admin text (copy‑ready)

- **Panel title:** `Conditional Logic (Free Addon)`
- **Toggle label:** `Enable conditional visibility for this field`
- **Empty state:** `No rules defined. Click Add Rule to show this field only when conditions are met.`
- **Validation error:** `Circular dependency detected: this rule would create a loop. Please revise.`
- **Preview button:** `Preview visibility`
- **Global setting:** `Enable Conditional Logic Addon (site-wide)`

---

## 11. Localization, licensing, and compatibility

- **i18n**
  - All strings translatable via `__()` / `_e()` and text domain matching Fluent Forms.
- **GPL compatibility**
  - Ensure addon license is compatible with Fluent Forms and WordPress plugin repo rules if you plan to publish.
- **Compatibility matrix**
  - Test with latest 3 major WP versions and latest Fluent Forms free release.
- **Conflict handling**
  - Detect Pro presence and gracefully disable overlapping features (show notice: “Fluent Forms Pro detected — addon will operate in compatibility mode”).

---

## 12. Rollout, telemetry, and support

- **Feature flag**
  - Add a feature flag to enable/disable advanced features for staged rollout.
- **Telemetry (opt‑in)**
  - Collect anonymized usage stats (rules count, errors) with opt‑in consent.
- **Support docs**
  - Provide step‑by‑step guides: “Create a rule”, “Debug visibility”, “Server‑side validation”.
- **Changelog**
  - Maintain clear changelog and migration notes for schema updates.

---

## 13. Example rule JSON (for developers)

```json
{
  "rules": [
    {
      "id": "r1",
      "target_field": "field_12",
      "action": "show",
      "logic": "AND",
      "conditions": [
        { "field": "field_3", "operator": "is", "value": "A" },
        { "field": "field_7", "operator": "is not", "value": "" }
      ],
      "priority": 10,
      "enabled": true
    }
  ],
  "schema_version": 1
}
```

---

## 14. Implementation risks and mitigations

- **Risk:** Circular dependencies cause infinite loops.  
  **Mitigation:** Detect cycles at save time and block save with clear error.

- **Risk:** Performance on large forms.  
  **Mitigation:** Dependency map + incremental evaluation + debouncing.

- **Risk:** Accessibility regressions.  
  **Mitigation:** Automated a11y tests and manual screen reader checks.

- **Risk:** Conflicts with Pro features.  
  **Mitigation:** Compatibility mode when Pro is present; avoid duplicating Pro UI.

---

## 15. Prioritized development roadmap (sprint plan)

1. **Sprint 1 (MVP)** — Rule data model, editor UI, client evaluator, server validation, basic tests.
2. **Sprint 2** — AND/OR groups, preview, accessibility, container visibility.
3. **Sprint 3** — Conditional required/defaults, integration with email/webhook conditional feeds.
4. **Sprint 4** — Nested groups, import/export, REST endpoints, developer hooks.
5. **Sprint 5** — Visual flow builder, telemetry, documentation, publish.

---

- **Help me create a short Product Requirements Document (PRD) that can be handed off to Google AntiGravity to create my Plugin Addon for FluentForms** — copy the MVP, QoL, and roadmap sections into a one‑page PRD that states: *goal, target users, success metrics, scope (MVP vs later), and non‑goals.
  
  Ask me questions until you have all the information you need to setup AntiGravity with everything it needs to create the first version of my WordPress Plugin*
