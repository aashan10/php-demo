<?php

require_once TEMPLATE_PATH . 'common/header.html.php';

use App\Models\User;
use App\Utils\ParameterBag;

/** @var User $user */
/** @var ParameterBag $errors */

?>

<section class="page-section" id="profile">
    <div class="container">
        <!-- Profile Section Heading-->
        <h2 class="page-section-heading text-center text-uppercase text-secondary mb-0">
            Welcome, <?= htmlspecialchars($user->FirstName) ?>
        </h2>
        <div class="divider-custom">
            <div class="divider-custom-line"></div>
            <div class="divider-custom-icon"><i class="fas fa-user-check"></i></div>
            <div class="divider-custom-line"></div>
        </div>

        <!-- Profile Section Form-->
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <form action="/profile" method="post" enctype="multipart/form-data">
                    
                    <div class="form-floating mb-3">
                        <input class="form-control" 
                            id="FirstName" 
                            type="text" 
                            placeholder="First Name" 
                            name="FirstName"
                            value="<?= htmlspecialchars($user->FirstName) ?>" 
                            disabled />
                        <label for="FirstName">First Name</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input class="form-control" 
                            id="LastName" 
                            type="text" 
                            placeholder="Last Name" 
                            name="LastName"
                            value="<?= htmlspecialchars($user->LastName) ?>"
                            disabled />
                        <label for="LastName">Last Name</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input class="form-control" 
                            id="email" 
                            type="email" 
                            placeholder="name@example.com" 
                            name="username"
                            value="<?= htmlspecialchars($user->username) ?>"
                            disabled />
                        <label for="email">Email address</label>
                    </div>

                    <div class="mb-3">
                        <label for="profile_picture" class="form-label">Update Profile Picture</label>
                        <input class="form-control" type="file" id="profile_picture" name="profile_picture" accept="image/*">
                        <?php if ($errors->has('profile_picture')): ?>
                            <div class="text-danger mt-2">
                                <?php echo $errors->get('profile_picture'); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button class="btn btn-primary btn-xl" type="submit">Update Profile</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require_once TEMPLATE_PATH . 'common/footer.html.php'; ?>
