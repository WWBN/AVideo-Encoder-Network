<?php
$config = dirname(__FILE__) . '/../configuration.php';
if (!file_exists($config)) {
    header("Location: install/index.php");
}
require_once $config;
require_once '../objects/Streamer.php';
require_once '../objects/Login.php';
require_once '../objects/functions.php';
require_once $global['systemRootPath'] . 'objects/Encoder.php';

if (!empty($_POST['webSiteRootURL'])) {
    $_GET['webSiteRootURL'] = $_POST['webSiteRootURL'];
}
if (!empty($_POST['user'])) {
    $_GET['user'] = $_POST['user'];
}
if (!empty($_POST['pass'])) {
    $_GET['pass'] = $_POST['pass'];
}

$streamerURL = @$_GET['webSiteRootURL'];

if (!empty($_GET['webSiteRootURL']) && !empty($_GET['user']) && !empty($_GET['pass']) && empty($_GET['justLogin'])) {
    Login::logoff();
}

if (empty($streamerURL)) {
    $streamerURL = Streamer::getFirstURL();
}
if (Login::isLogged()) {
    $streamer = new Streamer(Login::getStreamerId());
}

$bestEncoder = json_decode(file_get_contents("{$global['webSiteRootURL']}view/getBestEncoder.php"));

$encoders = Encoder::getAll();
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="description" content="">
        <meta name="author" content="">

        <title>Encoder Agregator</title>
        <link rel="icon" href="view/img/favicon.png">
        <script src="view/js/jquery-3.2.0.min.js" type="text/javascript"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.5.0/Chart.bundle.min.js"></script>
        <link href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
        <link href="view/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
        <script src="view/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
        <link href="view/js/seetalert/sweetalert.css" rel="stylesheet" type="text/css"/>
        <script src="view/js/seetalert/sweetalert.min.js" type="text/javascript"></script>
        <script src="view/js/main.js" type="text/javascript"></script>
        <link href="view/css/style.css" rel="stylesheet" type="text/css"/>
    </head>

    <body>
        <?php
        if (!Login::canUpload()) {
            ?>
            <div class="row">
                <div class="col-xs-1 col-md-2"></div>
                <div class="col-xs-10 col-md-8 ">
                    <form class="form-compact well form-horizontal"  id="loginForm">
                        <fieldset>
                            <legend>Please sign in</legend>


                            <div class="form-group">
                                <label class="col-md-4 control-label">Streamer Site</label>
                                <div class="col-md-8 inputGroupContainer">
                                    <div class="input-group">
                                        <span class="input-group-addon"><i class="glyphicon glyphicon-globe"></i></span>
                                        <input  id="siteURL" placeholder="http://www.your-tube-site.com" class="form-control"  type="url" value="<?php echo $streamerURL; ?>" required >
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-md-4 control-label">User</label>
                                <div class="col-md-8 inputGroupContainer">
                                    <div class="input-group">
                                        <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
                                        <input  id="inputUser" placeholder="User" class="form-control"  type="text" value="<?php echo @$_GET['user']; ?>" required >
                                    </div>
                                </div>
                            </div>


                            <div class="form-group">
                                <label class="col-md-4 control-label">Password</label>
                                <div class="col-md-8 inputGroupContainer">
                                    <div class="input-group">
                                        <span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
                                        <input  id="inputPassword" placeholder="Password" class="form-control"  type="password" value="<?php echo @$_GET['pass']; ?>" >
                                    </div>
                                </div>
                            </div>
                            <!-- Button -->
                            <div class="form-group">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-success  btn-block" id="mainButton" ><span class="fa fa-sign-in"></span> Sign in</button>
                                </div>
                            </div>
                        </fieldset>

                    </form>
                </div>
                <div class="col-xs-1 col-md-2"></div>
            </div>
            <script>
                var encodedPass = <?php
        // if pass all parameters submit the form
        echo (!empty($streamerURL) && !empty($_GET['user']) && !empty($_GET['pass'])) ? 'true' : 'false';
        ?>;
                $(document).ready(function () {
                    $('#loginForm').submit(function (evt) {
                        evt.preventDefault();
                        modal.showPleaseWait();
                        $.ajax({
                            url: 'login',
                            data: {"user": $('#inputUser').val(), "pass": $('#inputPassword').val(), "siteURL": $('#siteURL').val(), "encodedPass": encodedPass},
                            type: 'post',
                            success: function (response) {
                                if (response.error) {
                                    modal.hidePleaseWait();
                                    swal("Sorry!", response.error, "error");
                                } else
                                if (!response.streamer) {
                                    modal.hidePleaseWait();
                                    swal("Sorry!", "We could not found your streamer site!", "error");
                                } else if (!response.isLogged) {
                                    modal.hidePleaseWait();
                                    swal("Sorry!", "Your user or password is wrong!", "error");
                                } else {
                                    document.location = document.location;
                                }
                            }
                        });
                        return false;
                    });
                    $('#inputPassword').keyup(function () {
                        encodedPass = false;
                    });
    <?php
// if pass all parameters submit the form
    if (!empty($streamerURL) && !empty($_GET['user']) && !empty($_GET['pass'])) {
        echo '$(\'#loginForm\').submit()';
    }
    ?>

                });
            </script>
            <?php
        } else {
            ?>

            <link href="view/bootgrid/jquery.bootgrid.min.css" rel="stylesheet" type="text/css"/>
            <script src="view/bootgrid/jquery.bootgrid.min.js" type="text/javascript"></script>



            <div class="container-fluid"> <!-- style="overflow:hidden" -->
                <div class="row">
                    <div class="col-md-12" style="overflow:auto">
                        <div id="MyAccountsTab" class="tabbable tabs-left">
                            <!-- Account selection for desktop - I -->
                            <ul  class="nav nav-tabs col-md-2">
                                <?php
                                foreach ($encoders as $value) {
                                    ?>

                                    <li <?php
                                    if ($bestEncoder->id == $value['id']) {
                                        echo 'class="active"';
                                    }
                                    ?>>
                                        <div data-target="#l<?php echo $value['id']; ?>" data-toggle="tab">
                                            <div class="ellipsis">
                                                <span class="account-type"><?php echo $value['name']; ?></span>
                                                <span id="recommended<?php echo $value['id']; ?>" class="label label-success recommended" style="display: none;"><i class="fa fa-check"></i> Recommended</span><br/>
                                                <span id="label<?php echo $value['id']; ?>" class="label label-danger">Offline</span> <span class="account-amount" id="queuesize<?php echo $value['id']; ?>" >Queue Size 0</span><br/>
                                                <a href="<?php echo $value['siteURL']; ?>" class="account-link"><?php
                                                    $parts = parse_url($value['siteURL']);
                                                    echo $parts["host"];
                                                    ?></a><br>
                                                <span id="ping<?php echo $value['id']; ?>" class="label label-default">Searching Ping ...</span>
                                                <span id="maxfilesize<?php echo $value['id']; ?>" class="label label-default">Max File Size 0Mb</span>
                                            </div>

                                        </div>
                                    </li>

                                    <?php
                                }
                                ?>
                            </ul>
                            <div class="tab-content col-md-10">

                                <?php
                                foreach ($encoders as $value) {
                                    ?>

                                    <div class="tab-pane <?php
                                    if ($bestEncoder->id == $value['id']) {
                                        echo 'active';
                                    }
                                    ?>" id="l<?php echo $value['id']; ?>"><!--style="padding-left: 60px; padding-right:100px"-->

                                        <div class="row">
                                            <div class="col-sm-12">
                                                <canvas id="canvas<?php echo $value['id']; ?>" rowId="<?php echo $value['id']; ?>" siteURL="<?php echo $value['siteURL']; ?>" class="ping" height="20"></canvas>
                                            </div>

                                            <div class="col-sm-12" style="height: 70vh;">
                                                <iframe src="<?php echo $value['siteURL']; ?>?noNavbar=1&webSiteRootURL=<?php echo urlencode($_SESSION["login"]->streamer); ?>&user=<?php echo $_SESSION["login"]->user; ?>&pass=<?php echo $_SESSION["login"]->pass; ?>" frameborder="0" style="overflow:hidden;height:100%;width:100%;" height="100%" width="100%"></iframe>
                                            </div>
                                        </div>

                                    </div>

                                    <?php
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <script>
                window.myLine = new Array();
                window.myCPUPie = new Array();
                window.myMEMPie = new Array();
                function addData(id, value) {
                    try {
                        window.myLine[id].data.labels.push("");
                        window.myLine[id].data.datasets.foreach(function (dataset) {
                            dataset.data.push(value);
                        });
                        window.myLine[id].update();
                    } catch (e) {

                    }
                }
                function removeData(id) {
                    try {
                        window.myLine[id].data.labels.shift();
                        window.myLine[id].data.datasets.foreach(function (dataset) {
                            dataset.data.shift();
                        });
                        window.myLine[id].update();
                    } catch (e) {

                    }
                }

                function getPing(id) {
                    $.ajax({
                        url: 'ping/' + id,
                        success: function (response) {
                            removeData(id);
                            if (response.value) {
                                addData(id, response.value);
                                $('#ping' + id).text("Ping: " + response.value + " ms");
                            }
                            setTimeout(function () {
                                getPing(id);
                            }, 3000);
                        }
                    });
                }

                function goOffline(id) {
                    $('#label' + id).removeClass("label-success");
                    $('#label' + id).addClass("label-danger");
                    $('#label' + id).text("Offline");
                }
                function goOnline(id) {
                    $('#label' + id).removeClass("label-danger");
                    $('#label' + id).addClass("label-success");
                    $('#label' + id).text("Online");
                }

                function getEncoder(id, siteURL) {
                    $.ajax({
                        url: siteURL + 'serverStatus',
                        success: function (response) {
                            if (typeof response == 'object') {
                                if (response) {
                                    goOnline(id)
                                } else {
                                    goOffline(id)
                                }

                                $('#queuesize' + id).text("Queue Size " + response.queue_size);
                                $('#maxfilesize' + id).text("Max File Size " + response.file_upload_max_size);

                            } else {
                                goOffline(id)
                            }
                            setTimeout(function () {
                                getEncoder(id, siteURL);
                            }, 2500);
                        },
                        error: function () {
                            goOffline(id);
                            setTimeout(function () {
                                getEncoder(id, siteURL);
                            }, 5000);
                        }

                    });
                }

                function getBestEncoder() {
                    $.ajax({
                        url: 'view/getBestEncoder.php',
                        success: function (response) {
                            $('.recommended').not("#recommended" + response.id).fadeOut();
                            $("#recommended" + response.id).fadeIn();
                            console.log(response);
                            setTimeout(function () {
                                getBestEncoder();
                            }, 10000);
                        }
                    });
                }

                $(document).ready(function () {
                    getBestEncoder();

    <?php
    foreach ($encoders as $value) {
        ?>

                        window.myLine[<?php echo $value['id']; ?>] = new Chart(document.getElementById("canvas<?php echo $value['id']; ?>").getContext("2d"), {
                            animation: false,
                            type: 'line',
                            data: {
                                labels: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                                datasets: [{
                                        backgroundColor: 'rgba(0,155, 0, 0.3)',
                                        borderColor: 'rgba(0,155, 0, 0.5)',
                                        data: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
                                    }]
                            },
                            options: {
                                legend: {
                                    display: false
                                },
                                responsive: true,
                                scales: {
                                    xAxes: [{
                                            display: false,
                                            scaleLabel: {
                                                display: false
                                            }
                                        }],
                                    yAxes: [{
                                            display: true,
                                            scaleLabel: {
                                                display: false
                                            }
                                        }]
                                }
                            }
                        });

                        getPing(<?php echo $value['id']; ?>);
                        getEncoder(<?php echo $value['id']; ?>, '<?php echo $value['siteURL']; ?>');

        <?php
    }
    ?>

                });

            </script>


            <?php
        }
        ?>
    </body>
</html>
