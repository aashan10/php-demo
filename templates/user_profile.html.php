<?php

require_once TEMPLATE_PATH . 'common/header.html.php';

use App\Models\User;

/** @var User $user */

?>

<section class="page-section">
    <div class="container">
        <!-- Profile Section Heading-->
        <h2 class="page-section-heading text-center text-uppercase text-secondary mb-0">
            Profile: <?= htmlspecialchars($user->FirstName . ' ' . $user->LastName) ?>
        </h2>
        <div class="divider-custom">
            <div class="divider-custom-line"></div>
            <div class="divider-custom-icon"><i class="fas fa-user"></i></div>
            <div class="divider-custom-line"></div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <p class="lead"><strong>Email:</strong> <?= htmlspecialchars($user->username) ?></p>
                <p class="lead"><strong>Address:</strong> <?= htmlspecialchars($user->Address) ?></p>
                
                <!-- In a real application, you might show a profile picture, posts, etc. -->

            </div>
        </div>
    </div>
</section>

<?php require_once TEMPLATE_PATH . 'common/footer.html.php'; ?>
