<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$isGuestMode = current_user() === null && !empty($_SESSION['guest_trial_mode']);
if ($isGuestMode) {
    header('Cache-Control: no-store, no-cache, must-revalidate');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
        && ($_GET['guest_draft_clear'] ?? '') === '1') {
        clear_guest_trial_draft();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curriculum Enhancer</title>
    <link rel="stylesheet" href="assets/theme.css">
    <?php require __DIR__ . '/partials/guest_draft_lifecycle_head.php'; ?>
</head>
<body>
    <main class="login-page landing-page">
        <section class="landing-hero" aria-labelledby="landing-title">
            <header class="landing-header">
                <nav class="landing-nav" aria-label="Main navigation">
                    <a class="landing-brand" href="landing.php"><span class="wordmark-mark" aria-hidden="true">C</span><span>Curriculum Enhancer</span></a>
                    <div class="landing-nav-actions">
                        <a href="#landing-pipeline">How it works</a>
                        <a href="login.php">Log in</a>
                        <a class="landing-signup" href="signup.php">Sign up</a>
                    </div>
                </nav>
            </header>
            <div class="landing-hero-content">
                <p class="eyebrow">Academic planning workspace</p>
                <h1 id="landing-title">Enhance your Curriculum Today!</h1>
                <p class="landing-hero-copy">Plan, generate, and enhance curriculums with industry-skill needs and human input.</p>
                <div class="landing-actions">
                    <a class="button" href="generated_curriculum.php?guest=1">Try it out</a>
                    <a class="button button-secondary" href="signup.php">Get started</a>
                    <a class="landing-text-link" href="login.php">Log in</a>
                </div>
            </div>
            <span class="landing-hero-index" aria-hidden="true">CURRICULUM / ENHANCE / GENERATE</span>
        </section>

        <div class="landing-content">
            <section class="landing-metrics" aria-label="Current curriculum dataset snapshot">
                <div class="landing-metric">
                    <strong>56</strong>
                    <span>industry skills tracked</span>
                </div>
                <div class="landing-metric">
                    <strong>192</strong>
                    <span>distinct canonical course titles</span>
                </div>
                <div class="landing-metric">
                    <strong>08</strong>
                    <span>benchmark institutions</span>
                </div>
                <p class="landing-metrics-note">Dataset snapshot · 06 Oct 2026</p>
            </section>

            <section class="landing-section" aria-labelledby="landing-features-title">
                <div class="landing-section-heading">
                    <p class="eyebrow">Curriculum planning tools</p>
                    <h2 id="landing-features-title">Built to work from evidence, not guesswork.</h2>
                </div>
                <div class="landing-features">
                    <article class="landing-feature">
                        <span class="landing-feature-index">01 / GENERATE</span>
                        <h3>Generate</h3>
                        <p>Create a new curriculum draft shaped by relevant course data and the skill areas that need attention.</p>
                        <div class="landing-feature-visual" aria-hidden="true"><span>COURSE EVIDENCE</span><span class="landing-visual-arrow">→</span><span>NEW DRAFT</span></div>
                    </article>
                    <article class="landing-feature">
                        <span class="landing-feature-index">02 / ENHANCE</span>
                        <h3>Enhance</h3>
                        <p>Review an existing curriculum and explore suggested improvements in context.</p>
                        <div class="landing-feature-visual" aria-hidden="true"><span>YOUR CURRICULUM</span><span class="landing-visual-arrow">→</span><span>REVIEW/ENHANCE</span></div>
                    </article>
                    <article class="landing-feature landing-feature-evidence">
                        <span class="landing-feature-index">03 / EVIDENCE</span>
                        <h3>Skill-gap evidence</h3>
                        <p>Course data is compared with industry skill data to identify what may be missing, so recommendations have context beyond a generic AI response.</p>
                    </article>
                </div>
            </section>
        </div>

        <section class="landing-statement" aria-label="Evidence-grounded recommendations">
            <div class="landing-statement-content">
                <p>Curriculum recommendations connect real course data with industry skill needs, giving potential gaps useful context.</p>
            </div>
        </section>

        <div class="landing-content">
            <section class="landing-pipeline-section" id="landing-pipeline" aria-labelledby="landing-pipeline-title">
                <div class="landing-section-heading">
                    <p class="eyebrow">From evidence to review</p>
                    <h2 id="landing-pipeline-title">A visible path from comparison to decision.</h2>
                </div>
                <ol class="landing-pipeline">
                    <li><span class="landing-pipeline-number">01</span><span class="landing-pipeline-chip">COMPARE</span><strong>Course + skill data</strong></li>
                    <li><span class="landing-pipeline-number">02</span><span class="landing-pipeline-chip">IDENTIFY</span><strong>Weak coverage</strong></li>
                    <li><span class="landing-pipeline-number">03</span><span class="landing-pipeline-chip">REFINE</span><strong>Reviewed curriculum</strong></li>
                    <li><span class="landing-pipeline-number">04</span><span class="landing-pipeline-chip">VALIDATE</span><strong>Structure + evidence</strong></li>
                    <li><span class="landing-pipeline-number">05</span><span class="landing-pipeline-chip">REVIEW</span><strong>Human decision</strong></li>
                </ol>
            </section>

            <section class="landing-trial" aria-labelledby="landing-trial-title">
                <div>
                    <h2 id="landing-trial-title">Try it instantly, no account needed.</h2>
                    <p>Explore up to three attempts as guest across Generate and Enhance. Create an account when you’re ready to keep your work.</p>
                </div>
                <a class="button" href="generated_curriculum.php?guest=1">Try it out</a>
            </section>

            <section class="landing-closing" aria-labelledby="landing-closing-title">
                <h2 id="landing-closing-title">Make your next curriculum draft more refined.</h2>
                <div class="landing-actions">
                    <a class="button" href="generated_curriculum.php?guest=1">Try it out</a>
                    <a class="button button-secondary" href="signup.php">Sign up</a>
                </div>
            </section>
        </div>

        <footer class="landing-footer">
            <a class="landing-brand" href="landing.php"><span class="wordmark-mark" aria-hidden="true">C</span><span>Curriculum Enhancer</span></a>
            <p>&copy; 2026 Curriculum Enhancer. All rights reserved.</p>
        </footer>
    </main>
</body>
</html>
