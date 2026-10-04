# NBY IT Options

Site options plugin for NBY IT. It provides:

- a **Career** post type with ACF fields and a `[nby_careers]` listing, and
- a complete, built-in **career application system**: application form, secure resume upload, application records with statuses, email notifications, and an admin workflow.

No external form plugin is used.

---

## Requirements

- WordPress 6.0+
- PHP 7.4+ (tested on PHP 8.2 / 8.3)
- [Advanced Custom Fields](https://wordpress.org/plugins/advanced-custom-fields/) (free or Pro) to edit the Career fields

## Installation

1. Install and activate **Advanced Custom Fields**.
2. Copy the `nby-it-options` folder to `wp-content/plugins/`.
3. Activate **NBY IT Options** under Plugins.
4. Go to **NBY IT Options** in the admin menu.
5. Configure the **Careers** tab (see Settings below).
6. Create Careers under **NBY IT Options → Careers**.
7. Create a page called **Career Application**.
8. Add `[nby_career_application]` to that page, then select it as the **Application Page** in the Careers tab.
9. Add `[nby_careers]` to your Careers page, then select it as the **Careers Page**.

> Using Elementor? Add the shortcodes with Elementor's **Shortcode** widget. Make sure the application page is selected under **Application Page** so the plugin can detect it and turn off page caching for it.

## Shortcodes

### `[nby_careers]`

Lists published careers: number, employment type, title, location, deadline, experience level, salary and an **Apply** button.

Optional attributes override the settings:

```text
[nby_careers per_page="10" orderby="date|title|deadline|menu_order" order="DESC|ASC" show_expired="no|yes"]
```

- Expired careers are hidden unless **Show Expired Jobs** is enabled (or `show_expired="yes"`). When they are shown, their button reads **Applications Closed**.
- Pagination uses `?careers_page=2`.

### `[nby_career_application]`

Renders the application form for the career passed as `?job=senior-interior-designer` (the job's slug). It shows:

| Situation | Output |
|---|---|
| no `job` | "Please select a career position before applying." |
| unknown / draft / private career | "Position Not Available" |
| deadline passed | "Applications Closed" |
| valid open career | job summary and form |
| after a successful submission | success message and a link to the careers page |

`[nby_career_application job="senior-interior-designer"]` forces a specific career.

### `[nby_career_single]`

The two-column job page: **other open jobs on the left**, **the current job's full details and an Apply Now button on the right**. On screens narrower than 960px, the job details appear first and the other jobs below. Career pages use it automatically (see *Job single page* below). Add it to an Elementor single template if you design the job page yourself. `job="slug"` shows a specific job.

## Application URL / flow

```text
Apply button on [nby_careers]
  ↓
/career-application/?job=senior-interior-designer   (Application Page setting + job slug)
  ↓
Application form ([nby_career_application])
  ↓
Server-side validation → resume stored privately → Application created (status: New)
  ↓
Admin notification email (+ optional applicant confirmation)
  ↓
Redirect to the success message
```

The Apply URL is built from the **Application Page** setting and the job's **slug**, and is never hard-coded. Numeric job IDs are never shown in public URLs. WordPress still uses the ID internally (the slug is looked up on the server, and only published jobs match). Old `?job_id=123` links get a permanent (301) redirect to the slug URL. Changing a job's slug changes its Apply URL. If no page is selected, the plugin uses the first published page that contains `[nby_career_application]`. Developers can change it with the `nby_it_options_apply_url` filter.

## Settings (NBY IT Options)

**General**: Company Name (email signature), Primary Color (listing/form accent). Also shows the **Career Overview** (active/expired jobs, applications per status).

**Careers → Career Listing**: Enable Career Listing, Careers Page, Jobs Per Page, Order By, Order, Show Expired Jobs.

**Careers → Job Single Page**: Two-Column Layout (default on) and Other Jobs to Show (default 8).

**Careers → Application Settings**: Application Page, Recruitment Email (defaults to the site admin email), Send Applicant Confirmation (default Yes), Maximum Resume Size (default 5 MB, capped by the server limit), Allowed Resume Types (PDF/DOC/DOCX).

## Career fields (ACF)

Registered automatically in code (no ACF import needed):

| Field | Name | Type |
|---|---|---|
| Employment Type | `employment_type` | Select: Full Time, Part Time, Contract, Internship, Freelance |
| Experience Level | `experience_level` | Select: Fresher, Junior, Mid-Level, Senior, Expert |
| Job Location | `job_location` | Text |
| Salary | `salary` | Text (e.g. `$45k–$60k/year`) |
| Application Deadline | `application_deadline` | Date picker, stored as `Ymd`, displayed as `September 18, 2024` |

The deadline day itself is still open; applications close the next day (site timezone). An empty deadline means the job is always open. The old `apply_url` field is not used.

Values are read with `get_post_meta()`, so listings and applications keep working even if ACF is temporarily deactivated. An admin notice is shown in that case.

## Job single page

With **Two-Column Layout** on, `/career/{slug}/` uses `templates/single-career.php`. This keeps your theme's and Elementor's header and footer and renders `[nby_career_single]`:

- **Left:** other open (non-expired) jobs, linking to their pages, plus "View all careers".
- **Right:** employment type, title, location, experience, salary, deadline, an **Apply Now** button, the featured image, the job description, and a second Apply Now at the bottom. Expired jobs show **Applications Closed**.

Precedence: a theme `single-career.php`, or an Elementor Pro single template whose condition targets **Careers**, wins. Generic Elementor "All Singular" templates (like a blog post template) are ignored for careers. The shortlink (`?p=ID`) and REST links that reveal the job ID are removed from career pages.

## Applications (admin)

**NBY IT Options → Applications**

- Columns: Applicant, Position, Email, Phone, Status, Applied Date (sortable).
- Status links: All | New | Reviewing | Shortlisted | Interview | Rejected | Hired.
- Filters: career, status and month.
- Bulk actions: set status, move to trash.
- The details screen shows all applicant data, the cover letter, a **Download Resume** button and the **Application Status** selector (click **Update** to save).
- The menu shows a count of **New** applications.

Applicants are never told about status changes.

### Capabilities

Granted on activation to **Administrator** and **Editor** (change the roles with `nby_it_options_application_roles`):

`manage_career_applications`, `view_career_applications`, `edit_career_applications`, `edit_others_career_applications`, `edit_published_career_applications`, `delete_career_applications`, `delete_others_career_applications`, `delete_published_career_applications`, plus private/publish variants.

Careers use page-level capabilities, so editors and administrators can manage them but authors and contributors cannot. The settings page requires `manage_options`.

## Security & privacy

- **Server-side validation** of every field. Client-side checks are only for convenience.
- **Anti-spam**: WordPress nonce, honeypot field (`website`), signed minimum fill time (3s), and a limit of 5 successful applications per IP per hour (the IP is hashed and never stored).
- **Duplicates**: one application per email address per career. The check only uses the submitted email.
- **Resume upload**: the extension must be allowed, the size must be within the limit, the real MIME type is checked with `finfo`, and the file signature must match (`%PDF-`, OLE header for DOC, ZIP containing `word/document.xml` for DOCX). Browser-supplied names and types are ignored. Files are renamed to e.g. `john-doe-resume-20261004-k3j9x2m1q8zw4b7p.pdf`.
- **Resume storage**: resumes are stored **outside the web root** (`<folder above WordPress>/nby-it-options-private/resumes/`) when it is writable, so they cannot be requested by URL. If it is not writable, the plugin falls back to `wp-content/uploads/nby-it-options-private/resumes/` with `.htaccess`, `web.config` and `index.php` protection, and shows a warning on the Careers tab.
  - **nginx with the uploads fallback** needs a deny rule, because nginx ignores `.htaccess`:
    ```nginx
    location ^~ /wp-content/uploads/nby-it-options-private/ { deny all; return 404; }
    ```
  - **Backups**: if resumes are stored outside the web root, include that folder in your backups and migrations.
- **Downloads** go through `admin-post.php?action=nby_download_resume` and require the `view_career_applications` capability plus edit rights on the application. Logged-out visitors are sent to the login page.
- **Deletion**: permanently deleting an application deletes its resume. Moving it to the trash keeps the resume until the trash is emptied.
- The `career_application` post type is not public, not queryable, not searchable, not exportable and not in the REST API (`show_in_rest => false`).
- Applications are included in **Tools → Export/Erase Personal Data**, looked up by applicant email.
- The application page is sent with no-cache headers and `DONOTCACHEPAGE`. If your cache plugin ignores these, exclude the application page from caching (e.g. in NitroPack).

## Emails

- **Admin**: sent to the Recruitment Email, with subject `New Career Application — {Position}`. It contains the applicant details and links to the application and the protected resume download. The CV is **not attached**. Reply-To is set to the applicant.
- **Applicant** (optional): subject `Application Received — {Position}`. It contains no internal information.

Emails go through `wp_mail()`, so WP Mail SMTP or any other mailer applies. You can modify them with `nby_it_options_admin_email` and `nby_it_options_applicant_email`.

## Adding CAPTCHA later

You do not need to change the application system:

```php
add_action( 'nby_it_options_application_form_before_submit', function () {
	echo '<div class="cf-turnstile" data-sitekey="..."></div>';
} );

add_filter( 'nby_it_options_application_spam_check', function ( $result ) {
	// Verify $_POST['cf-turnstile-response'] with the provider...
	return $ok ? $result : new WP_Error( 'captcha', 'Please complete the verification.' );
} );
```

## Customizing templates

Copy any file from `templates/` to `your-theme/nby-it-options/` and edit it:

- `career-list.php`
- `career-application.php`: keep the field names, `NBY_IT_Options_Security::form_fields()` and the hidden `nby_action` / `job` inputs
- `application-success.php`
- `application-message.php`
- `single-career.php` (page wrapper) and `career-single.php` (two-column content)

CSS uses variables (`--nby-primary`, `--nby-border`, …) that you can override in your theme.

## Developer hooks

| Hook | Type | Purpose |
|---|---|---|
| `nby_it_options_modules` | filter | Register additional modules |
| `nby_it_options_post_types` | filter | Modify post type definitions |
| `nby_it_options_settings_tabs` | filter | Modify settings tabs |
| `nby_it_options_careers_query_args` | filter | Modify the listing query |
| `nby_it_options_apply_url` | filter | Change the Apply URL |
| `nby_it_options_single_layout` | filter | Force the two-column single layout on/off |
| `nby_it_options_has_elementor_career_template` | filter | Override Elementor template detection |
| `nby_it_options_employment_types` / `nby_it_options_experience_levels` | filter | Change select choices |
| `nby_it_options_application_statuses` | filter | Change statuses |
| `nby_it_options_application_validation_errors` | filter | Add validation rules |
| `nby_it_options_application_spam_check` | filter | CAPTCHA / extra checks |
| `nby_it_options_rate_limit`, `nby_it_options_rate_window`, `nby_it_options_min_fill_seconds` | filter | Anti-spam tuning |
| `nby_it_options_client_ip` | filter | Trusted proxy / Cloudflare IP |
| `nby_it_options_resume_dir`, `nby_it_options_store_outside_webroot` | filter | Resume storage location |
| `nby_it_options_application_created` | action | After an application is saved |
| `nby_it_options_application_status_changed` | action | After a status change |

## Architecture

```text
nby-it-options/
├── nby-it-options.php              Bootstrap, constants, activation hooks
├── includes/
│   ├── class-plugin.php            Wiring, admin menu, assets, ACF notice, templates
│   ├── class-module.php            Base class for content modules
│   ├── class-settings.php          Settings API page with modular tabs
│   ├── class-post-types.php        Registers post types declared by modules
│   ├── class-career.php            Career module (CPT, ACF fields, settings tab, query)
│   ├── class-application.php       Application module (CPT, statuses, admin, download)
│   ├── class-application-form.php  Form rendering, validation, submission
│   ├── class-file-upload.php       Resume validation and private storage
│   ├── class-email.php             Notification emails
│   ├── class-security.php          Nonce, honeypot, timing, rate limit
│   └── class-shortcodes.php        Shortcodes and conditional assets
├── admin/      css/admin.css, js/admin.js
├── public/     css/careers.css, css/career-single.css, css/application.css, js/application.js (no jQuery)
└── templates/  career-list.php, single-career.php, career-single.php, career-application.php, application-success.php, application-message.php
```

### Adding a module (e.g. Portfolio)

```php
class NBY_IT_Options_Portfolio extends NBY_IT_Options_Module {
	public function id() { return 'portfolio'; }
	public function post_types() { return array( 'portfolio' => array( /* register_post_type args */ ) ); }
	public function register() { /* hooks, settings tab via nby_it_options()->settings->add_tab() */ }
}
add_filter( 'nby_it_options_modules', function ( $modules ) {
	$modules['portfolio'] = new NBY_IT_Options_Portfolio();
	return $modules;
} );
```

## Uninstalling

Deactivating or deleting the plugin does **not** delete careers, applications or resumes. This prevents accidental data loss. To remove applicant data, delete the applications permanently (which removes their resumes) before removing the plugin.
