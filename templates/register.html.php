<?php require_once TEMPLATE_PATH . 'common/header.html.php'?>
<?php 

use App\Http\Request;
use App\Utils\ParameterBag;

/** @var ParameterBag $errors */ 
/** @var Request $request */
/** @var array $flash_errors */ // New variable

?>

<section class="page-section" id="contact">
    <div class="container">
        <!-- Contact Section Heading-->
        <h2 class="page-section-heading text-center text-uppercase text-secondary mb-0">Register</h2>
        <!-- Icon Divider-->
        <!-- Contact Section Form-->
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <!-- Display Flash Errors -->
                <?php if (!empty($flash_errors)):
                    <div class="alert alert-danger" role="alert">
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($flash_errors as $field => $message):
                                <li><?= htmlspecialchars($message) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form  method="post" action="/Register">
                    <!-- Name input-->

                    <div class="form-floating mb-3">
                        <input class="form-control" 
                            value="<?= htmlspecialchars($request->post->get('FirstName', '')) ?>"  
                            id="FirstName" 
                            type="text" 
                            placeholder="name@example.com" 
                            name="FirstName" />
                        <label for="FirstName">FirstName</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input class="form-control" 
                            value="<?= htmlspecialchars($request->post->get('LastName', '')) ?>"  
                            id="LastName" 
                            type="text" 
                            placeholder="name@example.com" 
                            name="LastName" />
                        <label for="LastName">LastName</label>
                    </div>


                    <div class="form-floating mb-3">
                        <input class="form-control" 
                            value="<?= htmlspecialchars($request->post->get('Address', '')) ?>"  
                            id="Address" 
                            type="text" 
                            placeholder="name@example.com" 
                            name="Address" />
                        <label for="Address">Address</label>
                    </div>

                    <!-- Email address input-->

                    <div class="form-floating mb-3">
                        <input class="form-control" 
                            value="<?= htmlspecialchars($request->post->get('username', '')) ?>"  
                            id="email" 
                            type="text" 
                            placeholder="name@example.com" 
                            name="username" />
                        <label for="email">Email address</label>
                    </div>

                    <!-- Password address input-->
                    <div class="form-floating mb-3">
                        <input class="form-control" 
                            id="password" 
                            type="password" 
                            placeholder="Enter your password" 
                            name="password" />
                        <label for="password">Password</label>
                    </div>
                    <!-- Submit Button-->
                    <button class="btn btn-primary btn-xl" id="submitButton" type="submit">Register</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require_once TEMPLATE_PATH . 'common/footer.html.php' ?>