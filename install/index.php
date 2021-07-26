<?php
require_once '../objects/functions.php';

function getPathToApplication() {
    return str_replace('install/index.php', '', $_SERVER["SCRIPT_FILENAME"]);
}

function getURLToApplication() {
    $url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $url = explode("install/index.php", $url);
    $url = $url[0];
    return $url;
}

$configFile = '../configuration.php';
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Install AVideo</title>
        <link rel="icon" href="../view/img/favicon.png">
        <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js" integrity="sha512-AA1Bzp5Q0K1KanKKmvN/4d3IRKVlv9PYgwFPvm32nPO6QS8yH1HO7LbgB1pgiOxPtfeg5zEn2ba64MUcqJx6CA==" crossorigin="anonymous"></script>
    </head>

    <body class="<?php echo $global['bodyClass']; ?>">
        <?php
        if (file_exists($configFile)) {
            require_once $configFile;
            if(!empty($global['webSiteRootURL'])){
                ?>
                <div class="container">
                    <h3 class="alert alert-success">
                        <span class="glyphicon glyphicon-ok-circle"></span>
                        Your system is installed, remove the <code><?php echo $global['systemRootPath']; ?>install</code> directory to continue
                        <hr>
                        <a href="<?php echo $global['webSiteRootURL']; ?>" class="btn btn-success btn-lg center-block">Go to the main page</a>
                    </h3>
                </div>
                <?php
            }
        } else {
            file_put_contents('', $configFile);
            if (!file_exists($configFile)) {
                ?>
                <div class="container">
                    <h3 class="alert alert-error">
                        <span class="glyphicon glyphicon-ok-circle"></span>
                        We could not create your <code><?php echo getPathToApplication(); ?>configuration.php</code> file
                        <hr>
                        <code>touch <?php echo getPathToApplication(); ?>configuration.php && chmod 777 <?php echo getPathToApplication(); ?>configuration.php</code>
                    </h3>
                </div>
                <?php
            }
        }
        if (file_exists($configFile)) {
            require_once $configFile;
        }

        if (empty($global['webSiteRootURL'])) {
            ?>
            <div class="container">
                <img src="../view/img/logo.png" alt="Logo" class="img img-responsive center-block"/>
                <div class="row">
                    <div class="col-md-12">
                        <form id="configurationForm">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="webSiteRootURL">Your Site URL</label>
                                    <input type="text" class="form-control" id="webSiteRootURL" placeholder="Enter your URL (http://yoursite.com)" value="<?php echo getURLToApplication(); ?>" required="required">
                                </div>
                                <div class="form-group">
                                    <label for="systemRootPath">System Path to Application</label>
                                    <input type="text" class="form-control" id="systemRootPath" placeholder="System Path to Application (/var/www/[application_path])" value="<?php echo getPathToApplication(); ?>" required="required">
                                </div>
                                <div class="form-group">
                                    <label for="allowedEncoders">
                                        Streamers Sites (One per line.)
                                        <button class="btn btn-xs btn-primary" data-toggle="popover"  type="button"
                                                title="What is this?"
                                                data-content="Only the listed sites will be allowed to use this encoder installation">
                                            <i class="glyphicon glyphicon-question-sign"></i>
                                        </button>
                                    </label>
                                    <textarea class="form-control" id="allowedEncoders" placeholder="Leave Blank for Public" value="">https://encoder.avideo.com/
        https://encoder1.avideo.com/</textarea>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="databaseHost">Database Host</label>
                                    <input type="text" class="form-control" id="databaseHost" placeholder="Enter Database Host" value="localhost" required="required">
                                </div>
                                <div class="form-group">
                                    <label for="databasePort">Database Port</label>
                                    <input type="text" class="form-control" id="databasePort" placeholder="Enter Database Port" value="3306" required="required">
                                </div>
                                <div class="form-group">
                                    <label for="databaseUser">Database User</label>
                                    <input type="text" class="form-control" id="databaseUser" placeholder="Enter Database User" value="root" required="required">
                                </div>
                                <div class="form-group">
                                    <label for="databasePass">Database Password</label>
                                    <input type="password" class="form-control" id="databasePass" placeholder="Enter Database Password">
                                </div>
                                <div class="form-group">
                                    <label for="databaseName">Database Name</label>
                                    <input type="text" class="form-control" id="databaseName" placeholder="Enter Database Name" value="aVideoNetwork" required="required">
                                </div>
                                <div class="form-group">
                                    <label for="createTables">Do you want to create database and tables?</label>

                                    <select class="" id="createTables">
                                        <option value="2">Create database and tables</option>
                                        <option value="1">Create only tables (Do not create database)</option>
                                        <option value="0">Do not create any, I will import the script manually</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-info" id="streamer" >

                                    <div class="form-group">
                                        <label for="siteURL">Streamer Site URL
                                            <button class="btn btn-xs btn-primary" data-toggle="popover"  type="button"
                                                    title="What is this?"
                                                    data-content="If you do not have Streamer Site yet, download it https://github.com/DanielnetoDotCom/AVideo">
                                                <i class="glyphicon glyphicon-question-sign"></i>
                                            </button>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-addon"><i class="glyphicon glyphicon-globe"></i></span>
                                            <input  id="siteURL" placeholder="http://www.your-tube-site.com" class="form-control"  type="url" value="" required >
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="inputUser">Streamer Site admin User</label>
                                        <div class="input-group">
                                            <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
                                            <input  id="inputUser" placeholder="User" class="form-control"  type="text" value="admin" required >
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="siteURL">Streamer Site admin Password</label>
                                        <div class="input-group">
                                            <span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
                                            <input  id="inputPassword" placeholder="Password" class="form-control"  type="password" value="" >
                                        </div>
                                    </div>
                                    <div class="alert alert-warning">
                                        If you do not have Streamer Site yet, download it <a href="https://github.com/DanielnetoDotCom/AVideo" target="_blank">here</a>. Then, please, go back here and finish this installation.
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Submit</button>
                        </form>
                    </div>
                </div>

            </div>
        <?php } ?>
        <script src="../view/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="../view/js/seetalert/sweetalert.min.js" type="text/javascript"></script>
        <script src="../view/js/main.js" type="text/javascript"></script>

        <script>
            $(function () {
                $('#configurationForm').submit(function (evt) {
                    evt.preventDefault();

                    modal.showPleaseWait();
                    var webSiteRootURL = $('#webSiteRootURL').val();
                    var systemRootPath = $('#systemRootPath').val();
                    var databaseHost = $('#databaseHost').val();
                    var databasePort = $('#databasePort').val();
                    var databaseUser = $('#databaseUser').val();
                    var databasePass = $('#databasePass').val();
                    var databaseName = $('#databaseName').val();
                    var allowedEncoders = $('#allowedEncoders').val();
                    var createTables = $('#createTables').val();

                    var siteURL = $('#siteURL').val();
                    var inputUser = $('#inputUser').val();
                    var inputPassword = $('#inputPassword').val();
                    $.ajax({
                        url: siteURL + '/login',
                        data: {"user": inputUser, "pass": inputPassword, "siteURL": siteURL},
                        type: 'post',
                        success: function (response) {
                            if (!response.isAdmin) {
                                modal.hidePleaseWait();
                                swal("Sorry!", "Your Streamer site, user or password is wrong!", "error");
                                $('#streamer').removeClass('alert-success');
                                $('#streamer').removeClass('alert-info');
                                $('#streamer').addClass('alert-danger');
                            } else {
                                $('#streamer').removeClass('alert-info');
                                $('#streamer').removeClass('alert-danger');
                                $('#streamer').addClass('alert-success');
                                console.log(webSiteRootURL + 'install/checkConfiguration.php');
                                $.ajax({
                                    url: webSiteRootURL + 'install/checkConfiguration.php',
                                    data: {
                                        webSiteRootURL: webSiteRootURL,
                                        systemRootPath: systemRootPath,
                                        allowedEncoders: allowedEncoders,
                                        databaseHost: databaseHost,
                                        databasePort: databasePort,
                                        databaseUser: databaseUser,
                                        databasePass: databasePass,
                                        databaseName: databaseName,
                                        createTables: createTables,
                                        siteURL: siteURL,
                                        inputUser: inputUser,
                                        inputPassword: inputPassword
                                    },
                                    type: 'post',
                                    success: function (response) {
                                        modal.hidePleaseWait();
                                        if (response.error) {
                                            swal("Sorry!", response.error, "error");
                                        } else {
                                            swal("Congratulations!", response.error, "success");
                                            window.location.reload(false);
                                        }
                                    },
                                    error: function (xhr, ajaxOptions, thrownError) {
                                        modal.hidePleaseWait();
                                        if (xhr.status == 404) {
                                            swal("Sorry!", "Your Site URL is wrong!", "error");
                                        } else {
                                            swal("Sorry!", "Unknow error!", "error");
                                        }
                                    }
                                });
                            }
                        }
                    });
                });
            });
        </script>
    </body>
</html>
