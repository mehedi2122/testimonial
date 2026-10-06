PRD — Testimonial SaaS MVP

1. Product Overview
   Product Name

Testimonial SaaS

Product Goal

Build a minimal SaaS platform inspired by Testimonial.to that allows businesses or individuals to:

Create testimonial collection spaces.
Share a public testimonial submission link.
Collect testimonials without requiring customers to create accounts.
Manage, favorite, hide, edit, and delete testimonials.
View basic analytics.
Create testimonial embeds for external websites.
Upgrade from a Free plan to a Pro plan using Stripe.

The product should prioritize simplicity, fast onboarding, clean UI, and a complete end-to-end workflow.

2. Recommended Technology Stack
   Backend: Laravel
   Frontend: Inertia.js with React
   UI: Tailwind CSS and shadcn/ui
   Database: MySQL
   Authentication: Laravel authentication
   Billing: Stripe with Laravel Cashier
   Charts: Recharts or an equivalent React charting library
   Queues: Laravel queues for webhook and notification processing
   Testing: Pest
   Deployment: Standard Laravel-compatible infrastructure
3. User Roles
   Account Owner

A registered user who can:

Create and manage Spaces.
Configure testimonial forms.
View submitted testimonials.
Manage testimonials.
View analytics.
Create testimonial embeds.
Manage billing and subscription.
Testimonial Submitter

A customer who does not need an account.

They can:

Open a public Space link.
View the Space information.
Complete the testimonial form.
Submit a testimonial.
Provide a rating if enabled.
Give social-sharing consent.
See a confirmation message after submission. 4. Authentication

Users should be able to:

Sign up.
Log in.
Log out.
Reset their password.

All dashboard functionality requires authentication.

Users must only be able to access their own Spaces, testimonials, embeds, and billing information.

Social login is out of scope for MVP.

5. Application Navigation

Authenticated users should have access to:

Dashboard
Spaces
Inbox
Embeds
Billing
Settings

The navigation should be simple and consistent across the application.

6. Dashboard

The Dashboard provides a high-level overview.

Statistics

Display:

Total testimonials
Total submissions/customers
Total Spaces
Current plan
Average rating, when ratings are enabled
Testimonial Collection Chart

Display the number of testimonials collected over time.

Filters:

Last 7 days
Last 30 days
Last 90 days
All time
Free Plan Upgrade Banner

Free users should see an upgrade notification such as:

Upgrade to Pro to create more Spaces and collect more testimonials.

The banner should not appear for Pro users.

7. Spaces

A Space is an individual testimonial collection page.

Create Space

The user can configure:

Basic Information
Title
Subtitle
Ask / testimonial request

Example:

Title: Customer Feedback
Subtitle: We'd love to hear from you
Ask: What do you think about our product?

8. Space Fields
   Default Fields

Every Space should have:

Field Default Required
Name Enabled Yes
Email Address Enabled Yes
Address Enabled Yes
Optional Predefined Fields

Users can enable:

Company Name
Social Links
Profile Photo

For optional fields, the Space owner can configure:

Enabled / Disabled
Required / Optional

Custom fields are not required for MVP.

9. Rating Configuration

When creating a Space, the user can choose:

Enable rating
Disable rating

If enabled, customers can provide a 1–5 star rating.

If disabled, no rating field should appear on the public form.

10. Space Themes

Provide a small number of predefined themes.

MVP themes:

Minimal
Modern
Clean

Theme selection affects the public testimonial submission page.

No custom theme builder is required for MVP.

11. Space Creation Success

After the user creates a Space:

Save the Space.
Generate a unique public URL.
Show a success page.
Display the public URL.
Provide Copy Link.
Provide View Space.

Example:

https://yourapp.com/s/acme-feedback

The public Space must not require authentication.

12. Public Testimonial Page

Customers access the Space using its public URL.

The page should display:

Space title
Subtitle
Ask/request
Configured fields
Rating, if enabled
Social sharing consent
Submit button

Only fields enabled by the Space owner should appear.

13. Testimonial Submission

Customers do not need to:

Create an account.
Log in.
Create a password.
Required Validation

All fields configured as required must be validated.

The backend must also validate the submission.

Social Sharing Consent

Include:

I give permission for this testimonial to be shared publicly or on social media.

This checkbox is optional.

Store the consent value with the testimonial.

14. Submission Success

After a successful submission, show a friendly confirmation page.

Example:

🎉 Boom! Your testimonial has been submitted.
You're officially awesome!

A small funny GIF/meme can optionally be displayed.

15. Testimonial Data

Each testimonial should contain:

ID
Space ID
Name
Email
Address
Company Name
Social Links
Profile Photo
Testimonial Text
Rating
Social Sharing Consent
Favorite Status
Wall of Love Status
Visibility Status
Created At
Updated At

Optional fields should be nullable.

16. Inbox

The Inbox is where the Space owner manages all testimonials.

Each testimonial should display:

Submitter name
Profile photo
Company name
Testimonial text
Rating
Submission date
Space
Favorite status
Wall of Love status
Visibility status 17. Testimonial Actions

Each testimonial should support the following actions.

Favorite

A user can favorite a testimonial.

Favorited testimonials should appear at the top of the relevant Space.

Wall of Love

A heart icon allows the user to mark/unmark a testimonial for Wall of Love.

More Actions

Use an ellipsis (...) menu containing:

Edit testimonial
Delete testimonial
Hide testimonial
Show testimonial

Deleting requires confirmation.

Hiding removes the testimonial from public displays but keeps it available in the owner's Inbox.

18. Testimonial Ordering

For public displays and Space-level listings:

Favorited testimonials appear first.
Remaining testimonials appear newest first.

Hidden testimonials must never appear publicly.

19. Embed Builder

Users should have a separate Embeds page.

The user selects a Space and creates an embeddable testimonial widget.

Supported Styles

MVP should support:

Masonry
Carousel 20. Embed Configuration

Users can configure:

Space
Layout/style
Dark mode
Animation
Background color
Show/hide rating
Show/hide profile photo
Number of testimonials displayed

Keep the configuration minimal.

21. Live Embed Preview

The Embed Builder should provide a live preview.

When the user changes:

Layout
Dark mode
Animation
Background color
Display options

the preview should update immediately.

The preview should use real testimonials from the selected Space where available.

22. Embed Code

After configuration, generate an embeddable code snippet.

Preferred implementation:

<script src="https://yourapp.com/embed.js"></script>

<div
    data-testimonial-widget="SPACE_ID"
    data-style="masonry">
</div>

An iframe-based implementation may be used if it provides better isolation and reliability.

The user should be able to:

View the code.
Copy the code.
Paste it into an external website. 23. Public Embed

The embedded widget must:

Work without authentication.
Work on external websites.
Be responsive.
Only display publicly visible testimonials.
Respect the selected Space.
Respect the user's configuration.
Work on desktop and mobile. 24. Billing & Pricing

The application uses a Freemium model.

Free Plan

$0/month

Limits:

Maximum 3 Spaces
Maximum 100 testimonials per Space
Pro Plan

$9.99/month

Limits:

Maximum 25 Spaces
Maximum 1,000 testimonials per Space

Only these two plans are required for MVP.

25. Plan Enforcement

The backend must enforce plan limits.

Space Limit

If a Free user already has 3 Spaces:

You've reached the Free plan limit. Upgrade to Pro to create more Spaces.

Testimonial Limit

If a Free Space reaches 100 testimonials:

This Space has reached its testimonial limit. Upgrade to Pro to collect more testimonials.

The limits must be enforced server-side, not only in the frontend.

26. Billing Page

The Billing page should display both plans.

Free
$0/month
3 Spaces
100 testimonials per Space
Upgrade button
Pro
$9.99/month
25 Spaces
1,000 testimonials per Space
Current plan indicator 27. Stripe Checkout

Use Stripe Checkout for the upgrade process.

Flow
User clicks Upgrade
↓
Laravel creates Stripe Checkout Session
↓
User goes to Stripe Checkout
↓
User enters payment details
↓
Payment completed
↓
Stripe redirects user back
↓
Success message displayed
↓
Stripe webhook confirms subscription
↓
User account becomes Pro

The application must not activate Pro status based only on the frontend redirect.

Stripe webhooks are the source of truth.

28. Stripe Webhooks

Implement webhook handling for at least:

Checkout completed
Subscription created
Subscription updated
Subscription canceled
Payment failure

Use Laravel queues where appropriate for webhook processing.

Verify Stripe webhook signatures.

29. Database Models

Minimum models:

User
id
name
email
password
plan
stripe_customer_id
stripe_subscription_id
created_at
updated_at
Space
id
user_id
title
subtitle
ask
slug
theme
rating_enabled
created_at
updated_at
SpaceField
id
space_id
field_type
enabled
required
Testimonial
id
space_id
name
email
address
company_name
social_links
profile_photo
content
rating
social_consent
is_favorite
is_wall_of_love
is_hidden
created_at
updated_at
Embed
id
user_id
space_id
style
dark_mode
animation
background_color
show_rating
show_profile_photo
created_at
updated_at 30. Analytics

Dashboard analytics should be calculated from application data.

Required metrics:

Total testimonials
Total submissions
Testimonials per day
Testimonials by Space
Average rating
Favorite count
Wall of Love count

Advanced analytics are out of scope for MVP.

31. Security

The application must:

Authenticate dashboard users.
Authorize every protected resource.
Prevent users from accessing other users' Spaces.
Prevent users from modifying other users' testimonials.
Validate all public submissions server-side.
Sanitize testimonial content before rendering.
Verify Stripe webhook signatures.
Avoid exposing customer email addresses publicly.
Protect uploaded profile images appropriately. 32. Main Pages
Public Pages
/login
/register
/forgot-password
/s/{space-slug}
/s/{space-slug}/success
Authenticated Pages
/dashboard
/spaces
/spaces/create
/spaces/{id}/edit
/inbox
/embeds
/embeds/create
/billing
/settings 33. Core API / Backend Operations

The exact API structure may follow Laravel conventions, but the system must support:

Authentication
Register
Login
Logout
Password Reset
Spaces
List Spaces
Create Space
View Space
Update Space
Delete Space
Testimonials
List Testimonials
Submit Public Testimonial
Edit Testimonial
Delete Testimonial
Favorite/Unfavorite
Wall of Love/Unmark
Hide/Show
Embeds
List Embeds
Create Embed
Update Embed
Generate Embed Configuration
Billing
View Subscription
Create Stripe Checkout
Process Stripe Webhook 34. UX Requirements

The UI should be:

Minimal
Clean
Modern
Responsive
Mobile-friendly
Easy for non-technical users

Include:

Loading states
Empty states
Success messages
Error messages
Form validation
Confirmation dialogs for destructive actions
Copy-to-clipboard buttons 35. MVP Development Phases
Phase 1 — Project Foundation
Create Laravel project.
Configure Inertia.js + React.
Configure Tailwind CSS + shadcn/ui.
Configure MySQL.
Configure authentication.
Create base dashboard layout.
Create initial database structure.
Checkpoint

User can register, log in, log out, and access the dashboard.

Phase 2 — Spaces

Implement:

Space CRUD
Space configuration
Fields
Required/optional fields
Rating configuration
Themes
Public Space URL
Success page
Checkpoint

User can create a Space and open its public URL.

Phase 3 — Testimonials

Implement:

Public testimonial form
Dynamic fields
Validation
Rating
Consent
Submission
Success page
Database storage
Checkpoint

A customer can submit a testimonial without an account, and the owner can see it.

Phase 4 — Inbox & Dashboard

Implement:

Inbox
Testimonial cards
Favorite
Wall of Love
Edit
Delete
Hide/show
Dashboard counters
Analytics chart
Date filters
Checkpoint

User can completely manage collected testimonials.

Phase 5 — Embeds

Implement:

Embed Builder
Masonry
Carousel
Dark mode
Animation
Background color
Display options
Live preview
Copy embed code
Public embed rendering
Checkpoint

A generated embed works on an external HTML website.

Phase 6 — Billing

Implement:

Free plan
Pro plan
Server-side limits
Billing page
Stripe Checkout
Stripe Customer
Stripe Subscription
Stripe Webhooks
Pro activation
Subscription status
Checkpoint

A user can successfully upgrade to Pro and see the updated plan in the application.

Phase 7 — Testing & Polish

Test:

Authentication
Authorization
Space creation
Field configuration
Public submission
Required fields
Rating
Testimonial management
Analytics
Embed rendering
Free limits
Stripe Checkout
Stripe Webhooks
Subscription state
Mobile responsiveness
Security

Use Pest for backend/application tests.

36. MVP Definition of Done

The MVP is considered complete when this entire workflow works:

Register
↓
Login
↓
Dashboard
↓
Create Space
↓
Configure Title / Subtitle / Ask
↓
Configure Fields
↓
Enable/Disable Rating
↓
Choose Theme
↓
Create Space
↓
Receive Public Link
↓
Customer Opens Public Link
↓
Customer Submits Testimonial
↓
Confirmation Page
↓
Owner Sees Testimonial in Inbox
↓
Favorite / Wall of Love / Edit / Hide / Delete
↓
Dashboard Statistics
↓
Create Embed
↓
Choose Masonry / Carousel
↓
Configure Embed
↓
Live Preview
↓
Copy Embed Code
↓
Embed Works on External Website
↓
Upgrade to Pro
↓
Stripe Checkout
↓
Stripe Webhook
↓
Pro Activated
↓
Billing Shows Active Pro Plan 37. Out of Scope for MVP

Do not implement initially:

Team members
Multiple account owners
Custom fields
Multiple paid plans
Annual billing
Coupons
Affiliate system
AI-generated testimonials
AI sentiment analysis
Automatic social media posting
Custom domains
White labeling
Native mobile applications
Advanced analytics
Customer API
Email automation
Complex moderation workflows

These can be considered for future versions.

38. AI Coding Agent Instructions

The coding agent should follow these rules:

Do not build the entire application at once.
Follow the development phases in order.
Complete and test one phase before starting the next.
Follow the technology stack defined in Section 2 exactly.
Do not introduce unnecessary technologies without a clear reason.
Use Laravel conventions and clean architecture.
Keep frontend components reusable.
Keep database relationships clear and normalized.
Enforce all business limits on the backend.
Never trust frontend-only validation for security or business rules.
Add tests for important business logic.
After each phase, verify that existing functionality still works.
Keep the UI minimal and consistent.
Do not implement features listed as out of scope.
When a requirement is ambiguous, document the assumption before implementation rather than silently inventing behavior.
