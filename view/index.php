<?php
$config = dirname(__FILE__) . '/../configuration.php';
if (!file_exists($config)) {
    header("Location: install/index.php");
    exit;
}
require_once $config;
if (empty($global['webSiteRootURL'])) {
    header("Location: install/index.php");
    exit;
}
require_once '../objects/Streamer.php';
require_once '../objects/Login.php';
require_once '../objects/functions.php';
require_once $global['systemRootPath'] . 'objects/Encoder.php';

$streamerURL = @$_REQUEST['webSiteRootURL'];

if (!empty($_REQUEST['webSiteRootURL']) && !empty($_REQUEST['user']) && !empty($_REQUEST['pass']) && empty($_REQUEST['justLogin'])) {
    Login::logoff();
}

if (empty($streamerURL)) {
    $streamerURL = Streamer::getFirstURL();
}
if (Login::isLogged()) {
    $streamer = new Streamer(Login::getStreamerId());
}

$arrContextOptions = array(
    "ssl" => array(
        "verify_peer" => false,
        "verify_peer_name" => false,
    ),
);

//var_dump(__LINE__, "{$global['webSiteRootURL']}view/getBestEncoder.php",  $serverPort !== '80' && $serverPort !== '443');exit;
$bestEncoder = json_decode(url_get_contents("{$global['webSiteRootURL']}view/getBestEncoder.php", stream_context_create($arrContextOptions)));

if (empty($bestEncoder)) {
    $bestEncoder = new stdClass();
    $bestEncoder->id = 0;
}

$encoders = Encoder::getAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Encoder Network</title>

    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $streamerURL; ?>videos/favicon.png">
    <link rel="icon" type="image/png" href="<?php echo $streamerURL; ?>videos/favicon.png">
    <link rel="shortcut icon" href="<?php echo $streamerURL; ?>videos/favicon.ico" sizes="16x16,24x24,32x32,48x48,144x144">
    <meta name="msapplication-TileImage" content="<?php echo $streamerURL; ?>videos/favicon.png">

    <link href="<?php echo $streamerURL; ?>node_modules/@fortawesome/fontawesome-free/css/all.min.css" rel=" stylesheet" crossorigin="anonymous">
    <script src="<?php echo $streamerURL; ?>node_modules/jquery/dist/jquery.min.js" type="text/javascript"></script>
    <script src="<?php echo $streamerURL; ?>node_modules/chart.js/dist/chart.umd.js"></script>

    <script src="<?php echo $streamerURL; ?>view/js/script.js" type="text/javascript"></script>
    <link href="<?php echo $streamerURL; ?>view/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <script src="<?php echo $streamerURL; ?>view/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo $streamerURL; ?>node_modules/sweetalert/dist/sweetalert.min.js" type="text/javascript"></script>
    <script src="<?php echo $streamerURL; ?>node_modules/js-cookie/dist/js.cookie.js" type="text/javascript"></script>

    <script src="<?php echo $global['webSiteRootURL']; ?>view/js/main.js?<?php echo filectime($global['systemRootPath'] . "view/js/main.js"); ?>" type="text/javascript"></script>
    <link href="<?php echo $global['webSiteRootURL']; ?>view/css/style.css?<?php echo filectime($global['systemRootPath'] . "view/css/style.css"); ?>" rel="stylesheet" type="text/css" />

    <link href="<?php echo $streamerURL; ?>view/css/main.css" rel="stylesheet" crossorigin="anonymous">
    <link href="<?php echo $streamerURL; ?>view/theme.css.php" rel="stylesheet" type="text/css" />

</head>

<body>
    <?php
    if (!Login::canUpload()) {
    ?>
        <div class="row">
            <div class="col-xs-1 col-md-2"></div>
            <div class="col-xs-10 col-md-8 ">
                <form class="form-compact well form-horizontal" id="loginForm">
                    <fieldset>
                        <legend>Please sign in</legend>


                        <div class="form-group">
                            <label class="col-md-4 control-label">Streamer Site</label>
                            <div class="col-md-8 inputGroupContainer">
                                <div class="input-group">
                                    <span class="input-group-addon"><i class="glyphicon glyphicon-globe"></i></span>
                                    <input id="siteURL" placeholder="http://www.your-tube-site.com" class="form-control" type="url" value="<?php echo $streamerURL; ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-md-4 control-label">User</label>
                            <div class="col-md-8 inputGroupContainer">
                                <div class="input-group">
                                    <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
                                    <input id="inputUser" placeholder="User" class="form-control" type="text" value="<?php echo @$_REQUEST['user']; ?>" required>
                                </div>
                            </div>
                        </div>


                        <div class="form-group">
                            <label class="col-md-4 control-label">Password</label>
                            <div class="col-md-8 inputGroupContainer">
                                <div class="input-group">
                                    <span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
                                    <input id="inputPassword" placeholder="Password" class="form-control" type="password" value="<?php echo @$_REQUEST['pass']; ?>">
                                </div>
                            </div>
                        </div>
                        <!-- Button -->
                        <div class="form-group">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-success  btn-block" id="mainButton"><span class="fa fa-sign-in"></span> Sign in</button>
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
                                echo (!empty($streamerURL) && !empty($_REQUEST['user']) && !empty($_REQUEST['pass'])) ? 'true' : 'false';
                                ?>;
            $(document).ready(function() {
                $('#loginForm').submit(function(evt) {
                    evt.preventDefault();
                    modal.showPleaseWait();
                    $.ajax({
                        url: 'login',
                        data: {
                            "user": $('#inputUser').val(),
                            "pass": $('#inputPassword').val(),
                            "siteURL": $('#siteURL').val(),
                            "encodedPass": encodedPass
                        },
                        type: 'post',
                        success: function(response) {
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
                                var url = new URL(document.location);
                                url.searchParams.append('justLogin', 1);
                                if (typeof response.PHPSESSID !== 'undefined' && response.PHPSESSID) {
                                    url.searchParams.append('PHPSESSID', response.PHPSESSID);
                                }
                                document.location = url;
                            }
                        }
                    });
                    return false;
                });
                $('#inputPassword').keyup(function() {
                    encodedPass = false;
                });
                <?php
                // if pass all parameters submit the form
                if (!empty($streamerURL) && !empty($_REQUEST['user']) && !empty($_REQUEST['pass'])) {
                    echo '$(\'#loginForm\').submit()';
                }
                ?>

            });
        </script>
    <?php
    } else {
        include 'navbar.php';
    ?>
        <div class="container-fluid"> <!-- style="overflow:hidden" -->
            <div class="row">
                <div class="col-md-12" style="overflow:auto">
                    <div id="MyAccountsTab" class="tabbable tabs-left">
                        <!-- Account selection for desktop - I -->
                        <ul class="nav nav-tabs col-md-2 col-sm-3" style="z-index: 2; ">
                            <?php
                            foreach ($encoders as $value) {
                            ?>

                                <li <?php
                                    if ($bestEncoder->id == $value['id']) {
                                        echo 'class="active"';
                                    }
                                    ?> style="cursor: pointer;">
                                    <div data-target="#l<?php echo $value['id']; ?>" data-toggle="tab">
                                        <div class="ellipsis">
                                            <span class="account-type"><?php echo $value['name']; ?></span>
                                            <div class="clearfix"></div>
                                            <span id="recommended<?php echo $value['id']; ?>" class="label label-success recommended" style="display: none;">
                                                <i class="fa fa-check"></i> Recommended
                                            </span>
                                            <span id="label<?php echo $value['id']; ?>" class="label label-danger">Offline</span>
                                                <div class="clearfix hidden-xs"></div>
                                                <span class="account-amount" id="queuesize<?php echo $value['id']; ?>">Queue Size 0 </span> / <span class="account-amount" id="concurrent<?php echo $value['id']; ?>">Concurrent 1 </span>
                                                <div class="clearfix"></div>
                                                <a href="<?php echo $value['siteURL']; ?>" class="account-link">
                                                    <?php
                                                    $parts = parse_url($value['siteURL']);
                                                    echo $parts["host"];
                                                    ?>
                                                </a>
                                                <div class="clearfix  hidden-xs"></div>
                                                <span id="ping<?php echo $value['id']; ?>" class="label label-default">Searching Ping ...</span>
                                                <span id="maxfilesize<?php echo $value['id']; ?>" class="label label-default">Max File Size 0Mb</span>
                                        </div>

                                    </div>
                                </li>

                            <?php
                            }
                            ?>
                        </ul>
                        <div class="tab-content col-md-10 col-sm-9">
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
                                            <canvas id="canvas<?php echo $value['id']; ?>" rowId="<?php echo $value['id']; ?>" siteURL="<?php echo $value['siteURL']; ?>" class="ping" height="30"></canvas>
                                        </div>
                                        <div class="col-sm-12" style="min-height: 70vh;">
                                            <iframe src="<?php echo $value['siteURL']; ?>?noNavbar=1&webSiteRootURL=<?php echo urlencode($_SESSION["login"]->streamer); ?>&user=<?php echo $_SESSION["login"]->user; ?>&pass=<?php echo $_SESSION["login"]->pass; ?>" frameborder="0" style="overflow:hidden;height:100vh;width:100%;" height="100%" width="100%"></iframe>
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
                    Array.prototype.forEach.call(window.myLine[id].data.datasets, dataset => {
                        dataset.data.push(value);
                    });
                    window.myLine[id].update();
                } catch (e) {

                }
            }

            function removeData(id) {
                try {
                    window.myLine[id].data.labels.shift();
                    Array.prototype.forEach.call(window.myLine[id].data.datasets, dataset => {
                        dataset.data.shift();
                    });
                    window.myLine[id].update();
                } catch (e) {

                }
            }
            async function pingJS(id, siteURL) {
                const start = new Date().getTime();
                var timeOut = 60000; // Set initial timeout to 1 minute
                const controller = new AbortController(); // Create a new instance for this request
                const timeoutId = setTimeout(() => controller.abort(), timeOut); // Set the timeout

                try {
                    const response = await fetch(siteURL, {
                        mode: 'no-cors',
                        signal: controller.signal // Attach the signal to the request
                    });
                    clearTimeout(timeoutId); // Clear the timeout since the fetch was successful

                    const end = new Date().getTime();
                    const ms = end - start;
                    addData(id, ms);
                    $('#ping' + id).text("Ping: " + ms + "ms");
                    timeOut = 10000; // Set new timeout for the next ping
                } catch (e) {
                    clearTimeout(timeoutId); // Clear the timeout since the fetch has concluded (either success or error)

                    // Handle fetch errors, including aborts
                    if (e.name === 'AbortError') {
                        console.error(`Ping to ${siteURL} timed out:`, e);
                        $('#ping' + id).text("Ping: Timeout");
                    } else {
                        console.error(`Error pinging ${siteURL}:`, e);
                    }
                }

                // Set the next ping
                setTimeout(function() {
                    pingJS(id, siteURL);
                }, timeOut);
            }

            async function pingPHP(id) {
                $.ajax({
                    url: 'ping/' + id,
                    success: function(response) {
                        removeData(id);
                        var timeOut = 120000; // 2 min
                        if (typeof response !== 'undefined' && response) {
                            if (response.value) {
                                addData(id, response.value);
                                $('#ping' + id).text("Ping: " + response.value + " ms");
                            }
                            timeOut = 30000;
                        }
                        setTimeout(function() {
                            pingPHP(id);
                        }, timeOut);
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

            var getEncoderTimout = [];

            function getEncoder(id, siteURL) {
                clearTimeout(getEncoderTimout[id]);
                $.ajax({
                    url: siteURL + 'serverStatus',
                    timeout: 1000,
                    success: function(response) {
                        if (typeof response == 'object') {
                            if (response) {
                                goOnline(id)
                            } else {
                                goOffline(id)
                            }

                            $('#queuesize' + id).text("Queue Size " + response.queue_size);
                            $('#concurrent' + id).text("Concurrent " + response.concurrent);
                            $('#maxfilesize' + id).text("Max File Size " + response.file_upload_max_size);

                        } else {
                            goOffline(id)
                        }
                        getEncoderTimout[id] = setTimeout(function() {
                            getEncoder(id, siteURL);
                        }, 5000);
                    },
                    error: function() {
                        goOffline(id);
                        getEncoderTimout[id] = setTimeout(function() {
                            getEncoder(id, siteURL);
                        }, 15000);
                    }

                });
            }

            function getBestEncoder() {
                $.ajax({
                    url: 'view/getBestEncoder.php',
                    success: function(response) {
                        $('.recommended').not("#recommended" + response.id).fadeOut();
                        $("#recommended" + response.id).fadeIn();
                        console.log(response);
                        setTimeout(function() {
                            getBestEncoder();
                        }, 30000);
                    }
                });
            }

            $(document).ready(function() {
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
                                backgroundColor: 'rgba(253,198,0, 0.3)',
                                borderColor: 'rgba(253,198,0,0.5)',
                                data: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    display: false,
                                },
                                title: {
                                    display: false,
                                }
                            },
                            legend: {
                                display: false // This should hide the legend (the label rectangle at the top)
                            },
                            tooltips: {
                                enabled: false // This should hide tooltips which can also appear as rectangles
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
                                    },
                                    ticks: {
                                        beginAtZero: true,
                                        min: 0, // Minimum value of y-axis
                                        //max: 1000, // Maximum value of y-axis
                                        stepSize: 100,
                                    }
                                }]
                            }
                        }

                    });

                    pingJS(<?php echo $value['id']; ?>, '<?php echo $value['siteURL']; ?>view/img/favicon.ico');
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