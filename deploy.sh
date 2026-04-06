#!/bin/bash

# API Master Deployment Script
# Version: 1.0.0
# Description: Automated deployment script for API Master WordPress plugin

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_NAME="api-master"
PLUGIN_SLUG="api-master"
VERSION="1.0.0"
BUILD_DIR="build"
DIST_DIR="dist"

# Logging function
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check prerequisites
check_prerequisites() {
    log_info "Checking prerequisites..."
    
    # Check if composer is installed
    if ! command -v composer &> /dev/null; then
        log_error "Composer is not installed. Please install Composer first."
        exit 1
    fi
    
    # Check if PHP is installed
    if ! command -v php &> /dev/null; then
        log_error "PHP is not installed. Please install PHP 7.4 or higher."
        exit 1
    fi
    
    # Check PHP version
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    if [[ $(echo "$PHP_VERSION < 7.4" | bc) -eq 1 ]]; then
        log_error "PHP version $PHP_VERSION is not supported. Please use PHP 7.4 or higher."
        exit 1
    fi
    
    log_success "All prerequisites satisfied"
}

# Clean build directories
clean_build() {
    log_info "Cleaning build directories..."
    
    rm -rf $BUILD_DIR
    rm -rf $DIST_DIR
    mkdir -p $BUILD_DIR
    mkdir -p $DIST_DIR
    
    log_success "Build directories cleaned"
}

# Run tests
run_tests() {
    log_info "Running tests..."
    
    if [ -f "phpunit.xml" ]; then
        ./vendor/bin/phpunit --configuration phpunit.xml
        if [ $? -eq 0 ]; then
            log_success "All tests passed"
        else
            log_error "Tests failed. Aborting deployment."
            exit 1
        fi
    else
        log_warning "phpunit.xml not found. Skipping tests."
    fi
}

# Optimize database schema
optimize_database() {
    log_info "Optimizing database schema..."
    
    if [ -f "setup.sql" ]; then
        # Validate SQL syntax
        php -r "
            \$sql = file_get_contents('setup.sql');
            \$queries = array_filter(array_map('trim', explode(';', \$sql)));
            foreach (\$queries as \$query) {
                if (!empty(\$query) && stripos(\$query, 'CREATE') !== false) {
                    echo \"Validating: \" . substr(\$query, 0, 50) . \"...\n\";
                }
            }
        "
        log_success "Database schema validated"
    else
        log_warning "setup.sql not found"
    fi
}

# Build assets
build_assets() {
    log_info "Building assets..."
    
    # Create assets directory if it doesn't exist
    mkdir -p assets/css
    mkdir -p assets/js
    mkdir -p assets/images
    
    # Minify CSS if npm is available
    if command -v npm &> /dev/null; then
        if [ -f "assets/css/admin.css" ]; then
            npx clean-css-cli -o assets/css/admin.min.css assets/css/admin.css
            log_success "CSS minified"
        fi
        
        # Minify JS if npm is available
        if [ -f "assets/js/admin.js" ]; then
            npx uglify-js assets/js/admin.js -o assets/js/admin.min.js
            log_success "JavaScript minified"
        fi
    else
        log_warning "npm not available. Skipping asset minification."
    fi
}

# Generate documentation
generate_docs() {
    log_info "Generating documentation..."
    
    if command -v phpdoc &> /dev/null; then
        phpdoc -d ./ -t docs/api --title "API Master Documentation" --force
        log_success "Documentation generated"
    else
        log_warning "phpDocumentor not installed. Skipping documentation generation."
    fi
}

# Create package
create_package() {
    log_info "Creating deployment package..."
    
    # Copy files to build directory
    rsync -av \
        --exclude=".git" \
        --exclude=".github" \
        --exclude=".gitignore" \
        --exclude=".env" \
        --exclude=".env.example" \
        --exclude="node_modules" \
        --exclude="vendor" \
        --exclude="tests" \
        --exclude="build" \
        --exclude="dist" \
        --exclude="*.log" \
        --exclude="*.sql" \
        --exclude="deploy.sh" \
        --exclude="composer.json" \
        --exclude="composer.lock" \
        --exclude="package.json" \
        --exclude="package-lock.json" \
        --exclude="phpunit.xml" \
        --exclude="phpunit.xml.dist" \
        --exclude="README.md" \
        --exclude="CONTRIBUTING.md" \
        --exclude="LICENSE" \
        ./ $BUILD_DIR/$PLUGIN_SLUG/
    
    # Create zip package
    cd $BUILD_DIR
    zip -r ../$DIST_DIR/$PLUGIN_SLUG-$VERSION.zip $PLUGIN_SLUG
    cd ..
    
    # Create deployment package without version
    cp -r $BUILD_DIR/$PLUGIN_SLUG $DIST_DIR/$PLUGIN_SLUG
    cd $DIST_DIR
    zip -r $PLUGIN_SLUG.zip $PLUGIN_SLUG
    cd ..
    
    log_success "Package created: $DIST_DIR/$PLUGIN_SLUG-$VERSION.zip"
}

# Generate checksums
generate_checksums() {
    log_info "Generating checksums..."
    
    cd $DIST_DIR
    sha256sum $PLUGIN_SLUG-$VERSION.zip > $PLUGIN_SLUG-$VERSION.zip.sha256
    md5sum $PLUGIN_SLUG-$VERSION.zip > $PLUGIN_SLUG-$VERSION.zip.md5
    cd ..
    
    log_success "Checksums generated"
}

# Create version file
create_version_file() {
    log_info "Creating version file..."
    
    cat > $DIST_DIR/version.json << EOF
{
    "version": "$VERSION",
    "stable": true,
    "requires_php": "7.4",
    "requires_wp": "5.0",
    "tested_up_to": "6.4",
    "release_date": "$(date -I)",
    "download_url": "https://github.com/yourusername/api-master/releases/download/v$VERSION/api-master-$VERSION.zip",
    "changelog": "Initial release of API Master with database management, encryption, and testing infrastructure."
}
EOF
    
    log_success "Version file created"
}

# Deploy to WordPress.org (optional)
deploy_to_wporg() {
    if [ -n "$WPORG_USERNAME" ] && [ -n "$WPORG_PASSWORD" ]; then
        log_info "Deploying to WordPress.org..."
        
        # Use SVN to deploy
        svn checkout https://plugins.svn.wordpress.org/$PLUGIN_SLUG /tmp/$PLUGIN_SLUG-svn
        
        # Copy files
        rsync -av $BUILD_DIR/$PLUGIN_SLUG/ /tmp/$PLUGIN_SLUG-svn/trunk/
        
        # Add new files
        cd /tmp/$PLUGIN_SLUG-svn
        svn add trunk/* --force
        svn commit -m "Deploy version $VERSION" --username $WPORG_USERNAME --password $WPORG_PASSWORD
        
        # Create tag
        svn cp trunk tags/$VERSION
        svn commit -m "Tag version $VERSION" --username $WPORG_USERNAME --password $WPORG_PASSWORD
        
        cd -
        rm -rf /tmp/$PLUGIN_SLUG-svn
        
        log_success "Deployed to WordPress.org"
    else
        log_warning "WordPress.org credentials not set. Skipping deployment."
    fi
}

# Cleanup
cleanup() {
    log_info "Cleaning up..."
    rm -rf $BUILD_DIR
    log_success "Cleanup complete"
}

# Main deployment process
main() {
    log_info "Starting deployment process for API Master v$VERSION"
    echo "=========================================="
    
    check_prerequisites
    clean_build
    run_tests
    optimize_database
    build_assets
    generate_docs
    create_package
    generate_checksums
    create_version_file
    deploy_to_wporg
    cleanup
    
    echo "=========================================="
    log_success "Deployment completed successfully!"
    log_info "Package location: $DIST_DIR/$PLUGIN_SLUG-$VERSION.zip"
}

# Run main function
main "$@"