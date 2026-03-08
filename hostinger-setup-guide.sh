#!/bin/bash

# =============================================================================
# 🚀 Hostinger Production Setup - Quick Reference Commands
# =============================================================================

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║       🌐 Hostinger Production Setup - Quick Commands          ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# =============================================================================
# CONFIGURATION
# =============================================================================
echo -e "${BLUE}📋 CONFIGURATION${NC}"
echo "──────────────────────────────────────────────────────────────────"
echo ""
echo "Before running these commands, update these variables:"
echo ""
echo -e "${YELLOW}# Your Hostinger username:${NC}"
echo "USERNAME='your-hostinger-username'"
echo ""
echo -e "${YELLOW}# Your domain public folder:${NC}"
echo "PUBLIC_DIR='/home/\$USERNAME/domains/education.medtrack.click/public_html'"
echo ""
echo -e "${YELLOW}# Your storage folder:${NC}"
echo "STORAGE_DIR='/home/\$USERNAME/domains/education.medtrack.click/storage'"
echo ""
echo "Press Enter to continue..."
read

# =============================================================================
# STEP 1: CREATE SYMBOLIC LINK
# =============================================================================
echo ""
echo -e "${BLUE}🔗 STEP 1: Create Symbolic Link${NC}"
echo "──────────────────────────────────────────────────────────────────"
echo ""
echo "Run this command on your Hostinger server:"
echo ""
echo -e "${GREEN}cd /home/\$USERNAME/domains/education.medtrack.click/public_html${NC}"
echo -e "${GREEN}ln -sfn ../storage/app/public storage${NC}"
echo ""
echo "Verify:"
echo -e "${GREEN}ls -la storage${NC}"
echo ""
echo "Expected output:"
echo "storage -> ../storage/app/public"
echo ""
echo "Press Enter to continue..."
read

# =============================================================================
# STEP 2: SET PERMISSIONS
# =============================================================================
echo ""
echo -e "${BLUE}🔐 STEP 2: Set Correct Permissions${NC}"
echo "──────────────────────────────────────────────────────────────────"
echo ""
echo "Run these commands:"
echo ""
echo -e "${GREEN}chmod -R 775 /home/\$USERNAME/domains/education.medtrack.click/storage/app/public${NC}"
echo -e "${GREEN}chmod -R 775 /home/\$USERNAME/domains/education.medtrack.click/public_html/storage${NC}"
echo ""
echo "Set ownership (replace USERNAME with your actual username):"
echo ""
echo -e "${GREEN}chown -R \$USERNAME:\$USERNAME /home/\$USERNAME/domains/education.medtrack.click/storage${NC}"
echo ""
echo "Press Enter to continue..."
read

# =============================================================================
# STEP 3: CREATE AUDIO DIRECTORY
# =============================================================================
echo ""
echo -e "${BLUE}📁 STEP 3: Create Audio Directory (if not exists)${NC}"
echo "──────────────────────────────────────────────────────────────────"
echo ""
echo -e "${GREEN}mkdir -p /home/\$USERNAME/domains/education.medtrack.click/storage/app/public/audio/sentences${NC}"
echo -e "${GREEN}chmod -R 775 /home/\$USERNAME/domains/education.medtrack.click/storage/app/public/audio${NC}"
echo ""
echo "Press Enter to continue..."
read

# =============================================================================
# STEP 4: UPDATE .ENV
# =============================================================================
echo ""
echo -e "${BLUE}⚙️  STEP 4: Update .env File${NC}"
echo "──────────────────────────────────────────────────────────────────"
echo ""
echo "Edit your production .env file and ensure these lines:"
echo ""
echo -e "${YELLOW}APP_URL=https://education.medtrack.click${NC}"
echo -e "${YELLOW}FILESYSTEM_DISK=public${NC}"
echo ""
echo "IMPORTANT: No trailing slash on APP_URL!"
echo ""
echo "After editing, clear config cache:"
echo ""
echo -e "${GREEN}php artisan config:clear${NC}"
echo -e "${GREEN}php artisan route:clear${NC}"
echo -e "${GREEN}php artisan cache:clear${NC}"
echo ""
echo "Press Enter to continue..."
read

# =============================================================================
# STEP 5: UPDATE .HTACCESS
# =============================================================================
echo ""
echo -e "${BLUE}📄 STEP 5: Update .htaccess${NC}"
echo "──────────────────────────────────────────────────────────────────"
echo ""
echo "Replace public/.htaccess with the updated version that includes:"
echo ""
echo "  # Priority rules for /storage/* files"
echo "  RewriteCond %{REQUEST_URI} ^/storage/"
echo "  RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI} -f"
echo "  RewriteRule ^ - [L]"
echo ""
echo "The updated .htaccess is already in your repository."
echo "Just upload it to: public_html/.htaccess"
echo ""
echo "Press Enter to continue..."
read

# =============================================================================
# STEP 6: TEST SETUP
# =============================================================================
echo ""
echo -e "${BLUE}🧪 STEP 6: Test Your Setup${NC}"
echo "──────────────────────────────────────────────────────────────────"
echo ""
echo "1. Create a test file:"
echo ""
echo -e "${GREEN}echo 'Storage works!' > /home/\$USERNAME/domains/education.medtrack.click/storage/app/public/test.txt${NC}"
echo ""
echo "2. Test in browser:"
echo ""
echo -e "${YELLOW}https://education.medtrack.click/storage/test.txt${NC}"
echo ""
echo "You should see: 'Storage works!'"
echo ""
echo "3. Test an audio file:"
echo ""
echo -e "${YELLOW}https://education.medtrack.click/storage/audio/sentences/exercise-1-a-mae.mp3${NC}"
echo ""
echo "The audio should play or download."
echo ""
echo "4. Run diagnostic script:"
echo ""
echo -e "${GREEN}php storage-diagnostic.php${NC}"
echo ""
echo "Press Enter to continue..."
read

# =============================================================================
# TROUBLESHOOTING
# =============================================================================
echo ""
echo -e "${BLUE}🔧 TROUBLESHOOTING${NC}"
echo "──────────────────────────────────────────────────────────────────"
echo ""
echo -e "${RED}Problem: 404 on storage files${NC}"
echo "Solution:"
echo "  • Check symlink: ls -la public_html/storage"
echo "  • Recreate symlink if broken"
echo "  • Verify file exists in ../storage/app/public/"
echo ""
echo -e "${RED}Problem: 403 Forbidden${NC}"
echo "Solution:"
echo "  • chmod -R 755 storage/app/public/audio"
echo "  • Check file ownership"
echo ""
echo -e "${RED}Problem: PostgreSQL UUID error${NC}"
echo "Solution:"
echo "  • Already fixed with ->whereUuid('exercise')"
echo "  • Clear route cache: php artisan route:clear"
echo ""
echo -e "${RED}Problem: Wrong URLs generated${NC}"
echo "Solution:"
echo "  • Check APP_URL in .env (no trailing slash)"
echo "  • Clear config: php artisan config:clear"
echo "  • Verify: Storage::disk('public')->url() usage"
echo ""
echo "Press Enter to finish..."
read

# =============================================================================
# SUMMARY
# =============================================================================
echo ""
echo -e "${GREEN}╔════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║                    ✅ SETUP COMPLETE!                          ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo "Your storage should now be configured correctly!"
echo ""
echo "Next steps:"
echo "  1. Upload files to Hostinger"
echo "  2. Run the commands above via SSH"
echo "  3. Test URLs in browser"
echo "  4. Check Laravel logs: tail -f storage/logs/laravel.log"
echo ""
echo "📖 For detailed guide, see: PRODUCTION_STORAGE_SETUP.md"
echo ""
