RSA Meat Trading
================

ROLES
-----
OWNER  (Owner Portal: /owner/)
  - Sales report dashboard (owner/dashboard.php) and the detailed Reports page (reports.html)
  - Create / disable / enable admin accounts and reset their passwords (owner/admins.php)
  - Can also open Orders, Create Order and Customers
  - Auto-logout after 30 minutes of inactivity

ADMIN  (normal login: /index.html)
  CAN:    Dashboard (order counts), Orders, Create Order, update order status, Customers
  CANNOT: Sales reports / revenue analytics, Owner Portal, create or manage any account
  - Auto-logout after 2 hours of inactivity
  - If the owner disables an admin, that admin is signed out immediately.

All of this is enforced on the server (api/*.php), not just by hiding menu items.

FIRST-TIME SETUP
----------------
1. config/db.php: put in your MySQL host, database name, username, password.
2. config/owner-auth.php: change OWNER_SETUP_KEY to your own long random phrase.
3. Open /owner/register-owner.php, enter that key, and create the OWNER account.
   (Anyone without the key is refused, and 5 wrong keys = 15-minute lockout.)
4. Sign in at /owner/ then go to "Manage Admins" and create the admin accounts.
5. Missing tables/columns (owners, owner_login_attempts, admin_login_attempts,
   users.is_active) are created automatically. Existing admins stay active.

AUDIT LOG (owner only: owner/audit.php)
---------------------------------------
Records who did what and when: order created, order status changed (from -> to),
admin/owner sign-ins (including failed attempts), product and price changes, admin
account changes. Admins cannot see or edit it. The table is created automatically.

PRODUCTS & PRICES (owner only: owner/products.php)
--------------------------------------------------
- Orders now use the price stored in the `products` table. The browser only sends
  product + quantity, so a price typed or tampered with by an admin is ignored.
- Admins pick a product from a list when creating an order; the price is filled in
  automatically and cannot be edited.
- The first time it runs, the price list is built from your past orders (latest price
  of each product). Open "Products & Prices" and review them.
- Changing a price only affects NEW orders; old orders keep their saved price.

CHANGES IN THIS VERSION
-----------------------
- REMOVED the public admin-register.php (anyone could create an admin). It now just
  redirects to the owner-only Manage Admins page.
- REMOVED owner/setup.php (it could never be used because of a mismatched key check).
  Use owner/register-owner.php instead.
- api/reports-data.php is now owner-only; admins get 403.
- Login attempts for admins are rate-limited (10 failures / 15 min per IP).

SECURITY NOTES
--------------
- The database password and OWNER_SETUP_KEY were visible in the files you shared.
  Change both: reset the MySQL password in your hosting panel and update config/db.php,
  and change OWNER_SETUP_KEY (it is harmless once an owner exists, but change it anyway).
- Do not put database passwords into HTML/JavaScript.
