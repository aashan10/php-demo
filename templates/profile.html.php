<?php require_once TEMPLATE_PATH . 'common/header.html.php' ?>

<section class="page-section" id="contact">
    <div class="container">
        <!-- Contact Section Heading-->
        <h2 class="page-section-heading text-center text-uppercase text-secondary mb-0">My Profile</h2>
        <!-- Icon Divider-->
        <!-- Contact Section Form-->
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <!-- * * * * * * * * * * * * * * *-->
                <!-- * * SB Forms Contact Form * *-->
                <!-- * * * * * * * * * * * * * * *-->
                <!-- This form is pre-integrated with SB Forms.-->
                <!-- To make this form functional, sign up at-->
                <!-- https://startbootstrap.com/solution/contact-forms-->
                <!-- to get an API token!-->
                <form method="post" action="/Profile" enctype="multipart/form-data">
                    <!-- Name input-->


                    <div class="form-floating mb-3">
                        <input class="form-control" value="<?= $request->post->get('Name', '') ?>" id="Name" type="text" placeholder="name@example.com" name="Name" />
                        <label for="Name">Name</label>



                        <div class="form-floating mb-3">
                            <input class="form-control" value="<?= $request->post->get('PublicEmail', '') ?>" id="PublicEmail" type="text" placeholder="name@example.com" name="PublicEmail" />
                            <label for="PublicEmail">PublicEmail address</label>

                            <?php if ($errors->has('PublicEmail')): ?>
                                <div class="error-message">
                                    <?php echo $errors->get('PublicEmail'); ?>

                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-floating mb-3">
                            <input class="form-control" value="<?= $request->post->get('Bio', '') ?>" id="Bio" type="text" placeholder="name@example.com" name="Bio" />
                            <label for="Bio">Bio</label>


                            <?php if ($errors->has('Bio')): ?>
                                <div class="error-message">
                                    <?php echo $errors->get('Bio'); ?>

                                </div>
                            <?php endif; ?>
                        </div>


                        <div class="form-floating mb-3">
                            <input class="form-control" value="<?= $request->post->get('URL', '') ?>" id="URL" type="text" placeholder="name@example.com" name="URl" />
                            <label for="URL">Website Url:</label>

                            <?php if ($errors->has('URL')): ?>
                                <div class="error-message">
                                    <?php echo $errors->get('URL'); ?>

                                </div>
                            <?php endif; ?>

                        </div>

                        <div class="form-floating mb-3">
                            <input class="form-control" value="<?= $request->post->get('Company', '') ?>" id="Company" type="text" placeholder="name@example.com" name="Company" />
                            <label for="Company">Company:</label>

                            <?php if ($errors->has('Company')): ?>
                                <div class="error-message">
                                    <?php echo $errors->get('Company'); ?>

                                </div>
                            <?php endif; ?>

                        </div>




                        <div class="form-floating mb-3">

                            <label for="File">Upload File :</label><br>
                            <input type="file" id="File" name="File"><br><br>

                            <button type="submit">Upload </button>
                            <?php if ($errors->has('File')): ?>
                                <div class="error-message">
                                    <?php echo $errors->get('File'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- Submit Button-->


                        <button class="btn btn-primary btn-xl" id="submitButton" type="submit">Submit</button>

                         <button class="nav-item mx-0 mx-lg-1"><a class="nav-link py-3 px-0 px-lg-3 rounded" href="/Register">Register</a></button>
                    
                </form>


</section>

<?php require_once TEMPLATE_PATH . 'common/footer.html.php' ?>