#!/bin/bash

# File to append aliases
BASHRC="$HOME/.bashrc"

# Aliases to add
ALIASES=$(cat <<'EOF'
# Magento Temp and Deploy Aliases
alias tempEn='bin/magento dev:temp:en && bin/magento c:f'
alias tempDis='bin/magento dev:temp:dis && bin/magento c:f'
alias mdeploy='bin/magento maintenance:enable && bin/magento setup:upgrade && bin/magento deploy:mode:set production && bin/magento c:f && bin/magento maintenance:disable'
EOF
)

# Check if aliases already exist (avoid duplicates)
if grep -q "alias tempEn=" "$BASHRC"; then
    echo "Aliases already present in $BASHRC"
else
    echo "$ALIASES" >> "$BASHRC"
    echo "Aliases added to $BASHRC"
    echo "Run: source ~/.bashrc"
    echo "Or open a new terminal to use: tempEn, tempDis, mdeploy"
fi
