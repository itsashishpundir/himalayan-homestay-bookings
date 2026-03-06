<?php
/**
 * Template Name: Legal Page (Plugin)
 *
 * A clean, text-focused template for Privacy Policy, Terms, etc.
 * Features a simple grey hero and central content column.
 *
 * @package Himalayan\Homestay\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
the_post();
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --legal-bg: #f8f9fa;
        --legal-text: #334155;
        --legal-heading: #0f172a;
        --legal-border: #e2e8f0;
        --brand: #e85e30;
    }

    .hhm-legal-page * {
        box-sizing: border-box;
        font-family: 'Inter', sans-serif;
    }

    /* ── SIMPLE GREY HERO ── */
    .hhm-legal-hero {
        background-color: var(--legal-bg);
        border-bottom: 1px solid var(--legal-border);
        padding: 64px 24px;
        text-align: center;
    }

    .hhm-legal-hero h1 {
        font-size: clamp(32px, 5vw, 48px);
        font-weight: 800;
        color: var(--legal-heading);
        margin: 0 0 16px;
        letter-spacing: -0.02em;
    }

    .hhm-legal-breadcrumb {
        font-size: 14px;
        color: #64748b;
        font-weight: 500;
    }

    .hhm-legal-breadcrumb a {
        color: var(--brand);
        text-decoration: none;
        transition: color 0.2s;
    }

    .hhm-legal-breadcrumb a:hover {
        color: #c94d22;
        text-decoration: underline;
    }

    .hhm-legal-breadcrumb span.separator {
        margin: 0 8px;
        color: #cbd5e1;
    }

    /* ── CONTENT AREA ── */
    .hhm-legal-content-wrap {
        background-color: #ffffff;
        padding: 64px 24px 80px;
    }

    .hhm-legal-content {
        max-width: 800px;
        margin: 0 auto;
        color: var(--legal-text);
        font-size: 16px;
        line-height: 1.8;
    }

    /* WordPress Editor Styles */
    .hhm-legal-content h2 {
        font-size: 28px;
        font-weight: 700;
        color: var(--legal-heading);
        margin: 48px 0 24px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--legal-border);
    }

    .hhm-legal-content h3 {
        font-size: 22px;
        font-weight: 600;
        color: var(--legal-heading);
        margin: 32px 0 16px;
    }

    .hhm-legal-content p {
        margin: 0 0 20px;
    }

    .hhm-legal-content ul, 
    .hhm-legal-content ol {
        margin: 0 0 24px;
        padding-left: 24px;
    }

    .hhm-legal-content li {
        margin-bottom: 8px;
    }

    .hhm-legal-content a {
        color: var(--brand);
        text-decoration: underline;
    }

    .hhm-legal-content a:hover {
        color: #c94d22;
        text-decoration: none;
    }

    .hhm-legal-content strong {
        color: var(--legal-heading);
        font-weight: 700;
    }

    .hhm-legal-last-updated {
        margin-top: 48px;
        padding-top: 24px;
        border-top: 1px dashed var(--legal-border);
        font-size: 14px;
        color: #94a3b8;
    }
</style>

<div class="hhm-legal-page">

    <!-- Hero Section -->
    <div class="hhm-legal-hero">
        <h1><?php the_title(); ?></h1>
        
        <div class="hhm-legal-breadcrumb">
            <a href="<?php echo esc_url( home_url() ); ?>">Home</a>
            <span class="separator">/</span>
            <span class="current"><?php the_title(); ?></span>
        </div>
    </div>

    <!-- Content Section -->
    <div class="hhm-legal-content-wrap">
        <div class="hhm-legal-content">
            <?php the_content(); ?>
            
            <div class="hhm-legal-last-updated">
                Last updated: <?php echo get_the_modified_date('F j, Y'); ?>
            </div>
        </div>
    </div>

</div>

<?php get_footer(); ?>
