Changelog
=========
#### 1.7.3 - 2026-05-19
- Added support for GA UTMs in cart recovery endpoint

#### 1.7.2 - 2026-05-12
- Added support for Wordpress 7.0

#### 1.7.1 - 2026-04-23
- Optimized the plugin package by removing internal configuration files

#### 1.7.0 - 2026-04-17
- Improved code based on QIT inspection
- Ensured one-time user metadata migration runs reliably upon plugin update
- Expose page context for upcoming functionalities

#### 1.6.7 - 2026-04-10
- Improved custom fields handling

#### 1.6.6 - 2026-04-01
- Improved CSS classes for marketing checkbox

#### 1.6.5 - 2026-02-23
- Fixed error for missing product in order
- Added error handling in webConnect integration

#### 1.6.4 - 2026-01-22
- Removed limitation for product types
- Improved order tracking to capture all new orders from the moment of creation

#### 1.6.3 - 2026-01-08
- Added limitation for product image and desc

#### 1.6.2 - 2026-01-07
- Fix in getting product images
- Fix in passing marketing consent from profile page

#### 1.6.1 - 2025-12-04
- Added support for Wordpress 6.9

#### 1.6.0 - 2025-11-20
- Added support for handling marketing consent on checkout-blocks

#### 1.5.5 - 2025-11-18
- Improved HTTP request isolation to prevent third-party plugin interference

#### 1.5.4 - 2025-10-21
- Fixed error handling
- Improved validation of configuration updates with remote verification service

#### 1.5.3 - 2025-09-02
- Improved cart management with additional cookie cart_id storage

#### 1.5.2 - 12-05-2025
- Added support for Wordpress 6.8.1

#### 1.5.1 - 11-04-2025
- Fixed arguments for rounding function (PHP8.x)

#### 1.5.0 - 20-03-2025
- Added support for filtering users and customers by the gr_updated_after parameter in /wp/v2/users and /wc/v3/customers API endpoints

#### 1.4.2 - 24-02-2025
- Fixed WebConnect event for cart (PHP8.x)

#### 1.4.1 - 10-02-2025
- Fixed WooCommerce cart handler

#### 1.4.0 - 31-01-2025
- Add rebuilding abandoned cart

#### 1.3.11 - 21-01-2025
- Fixed ContactForm 7 checkbox and select fields handling

#### 1.3.10 - 07-01-2025
- Added support for Wordpress 6.7.1

#### 1.3.9 - 31-07-2024
- Fixed accessing to empty terms

#### 1.3.8 - 18-07-2024
- Fixed error when receiving float instead of int

#### 1.3.7 - 03-07-2024
- Fixed convert date on null
- Make sure that item in order is product

#### 1.3.6 - 21-05-2024
- Fixed issue with session

#### 1.3.5 - 11-04-2024
- Verified, that WooCommerce plugin is active before profile_update hook handling

#### 1.3.4 - 12-03-2024
- Fixed security issues

#### 1.3.3 - 15-02-2024
- Fixed ContactForm 7 custom fields handling

#### 1.3.2 - 13-01-2024
- Fix issue with session

#### 1.3.1 - 11-01-2024
- Fix issue with type mismatch in order->get_total()

#### 1.3.0 - 29-12-2023
- Handled WooCommerce customer custom fields

#### 1.2.2 - 27-11-2023
- Added Web Connect events for cart and order

#### 1.2.1 - 08-11-2023
- Fixed an issue with the webhook when creating a new user directly from the dashboard
- Fixed Web Connect integration

#### 1.2.0 - 29-09-2023
- feat: order webhook can add contact     

#### 1.1.4 - 19-09-2023
- fix in recommendation integration

#### 1.1.3 - 08-09-2023
- handle tags in ContactForm 7 integration

#### 1.1.2 - 22-08-2023
- fixed WebConnect snippet

#### 1.1.1 - 17-08-2023
- added tracking code user identification

#### 1.1.0 - 25-07-2023
- marketing consent on checkout form
- marketing consent on WooCommerce registration form

#### 1.0.3 - 21-07-2023
- fix: session for admin
- fix: quantity cast
- fix: sites endpoint with get_home_url method for fetching urls

#### 1.0.2 - 05-07-2023
- fix: Recommendation payload

#### 1.0.1 - 13-06-2023
- fix: Logger

#### 1.0.0 - 06-06-2023
- feat: Logger

#### 0.0.20 - 29-05-2023
- feat: Sale price and dates in WooCommerce hooks

#### 0.0.19 - 23-05-2023
- feat: Product and variant status in WooCommerce hooks

#### 0.0.18 - 17-05-2023
- fix: Correct prices for hooks

#### 0.0.17 - 15-05-2023
- fix: Date timezone for hooks

#### 0.0.16 - 11-05-2023
- fix: WPHYBRID-73

#### 0.0.15 - 11-05-2023
- Refactor: WPHYBRID-67

#### 0.0.14 - 08-05-2023
- Refactor: WPHYBRID-67

#### 0.0.12 - 21-04-2023

- Integration with Contact Form 7