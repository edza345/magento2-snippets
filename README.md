# Disable 2FA fast

```
wget https://raw.githubusercontent.com/edza345/magento2-snippets/refs/heads/main/disable-2fa.php
php disable-2fa.php
rm disable-2fa.php
```

# Faster static content deploy(-j16)

```
wget https://raw.githubusercontent.com/edza345/magento2-snippets/refs/heads/main/fast-deployment.patch
patch -p1 < fast-deployment.patch
rm fast-deployment.patch
```

# Get Website Themes with paths

```
wget https://raw.githubusercontent.com/edza345/magento2-snippets/refs/heads/main/get-store-themes.php
php get-store-themes.php
rm get-store-themes.php
```

# Enable Check/Money Payment method and create Test customer account

```
wget https://raw.githubusercontent.com/edza345/magento2-snippets/refs/heads/main/local-checkout-customer-test-config.php
php local-checkout-customer-test-config.php
rm local-checkout-customer-test-config.php
```

# Unlock and set Active Admin users

Navigate with arrows and use spacebar to mark admins to unlock

Press Enter to apply 

```
wget https://raw.githubusercontent.com/edza345/magento2-snippets/refs/heads/main/unlock-admins.php
php local-checkout-customer-test-config.php
rm local-checkout-customer-test-config.php
```

