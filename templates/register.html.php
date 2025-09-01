<?php require_once TEMPLATE_PATH . 'common/header.html.php' ?>
<?php 

use App\Http\Request;
use App\Utils\ParameterBag;

/** @var ParameterBag $errors */ 
/** @var Request $request */

?>

<section class="page-section" id="contact">
    <div class="container">
        <!-- Contact Section Heading-->
        <h2 class="page-section-heading text-center text-uppercase text-secondary mb-0">Register</h2>
        <!-- Icon Divider-->
        <!-- Contact Section Form-->
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <form method="post" action="/Register">
                    <!-- Name input-->


                    <div class="form-floating mb-3">
                        <input class="form-control" value="<?= $request->post->get('FirstName', '') ?>" id="FirstName" type="text" placeholder="name@example.com" name="FirstName" />
                        <label for="FirstName">FirstName</label>

                        <?php if ($errors->has('FirstName')): ?>
                            <div class="error-message">
                                <?php echo $errors->get('FirstName'); ?>

                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-floating mb-3">
                        <input class="form-control" value="<?= $request->post->get('LastName', '') ?>" id="LastName" type="text" placeholder="name@example.com" name="LastName" />
                        <label for="LastName">LastName</label>


                        <?php if ($errors->has('LastName')): ?>
                            <div class="error-message">
                                <?php echo $errors->get('LastName'); ?>

                            </div>
                        <?php endif; ?>
                    </div>



                    <div class="form-floating mb-3">
                        <input class="form-control" value="<?= $request->post->get('Address', '') ?>" id="Address" type="text" placeholder="name@example.com" name="Address" />
                        <label for="Address">Address</label>

                        <?php if ($errors->has('Address')): ?>
                            <div class="error-message">
                                <?php echo $errors->get('Address'); ?>

                            </div>
                        <?php endif; ?>
                    </div>




                    <!-- Email address input-->

                    <div class="form-floating mb-3">
                        <input class="form-control" value="<?= $request->post->get('username', '') ?>" id="email" type="text" placeholder="name@example.com" name="username" />
                        <label for="email">Email address</label>

                        <?php if ($errors->has('username')): ?>
                            <div class="error-message">
                                <?php echo $errors->get('username'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- Password address input-->
                    <div class="form-floating mb-3">
                        <input class="form-control" id="password" type="password" placeholder="Enter your password" name="password" />
                        <label for="password">Password</label>

                        <?php if ($errors->has('password')): ?>
                            <div class="error-message">
                                <?php echo $errors->get('password'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- Submit Button-->
                    <button class="btn btn-primary btn-xl" id="submitButton" type="submit">Register</button>
                </form>
            </div>
        </div>
    </div>
</section>


<?php require_once TEMPLATE_PATH . 'common/footer.html.php' ?>
