<?php
$config = dirname(__FILE__) . '/../configuration.php';
require_once $config;
require_once '../objects/Streamer.php';
require_once '../objects/Login.php';
require_once '../objects/functions.php';
$streamerURL = @$_GET['webSiteRootURL'];
if (empty($streamerURL)) {
    $streamerURL = Streamer::getFirstURL();
}
if (Login::isLogged()) {
    $streamer = new Streamer(Login::getStreamerId());
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="description" content="">
        <meta name="author" content="">

        <title>YouPHPTube Encoder Agregator</title>
        <link rel="icon" href="view/img/favicon.png">
        <script src="view/js/jquery-3.2.0.min.js" type="text/javascript"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.5.0/Chart.bundle.min.js" integrity="sha256-+q+dGCSrVbejd3MDuzJHKsk2eXd4sF5XYEMfPZsOnYE=" crossorigin="anonymous"></script>

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
                                <label class="col-md-4 control-label">YouPHPTube Streamer Site</label>  
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
            <table id="gridEncoder" class="table table-condensed table-hover table-striped">
                <thead>
                    <tr>
                        <th data-column-id="siteURL" data-formatter="siteURL" data-width="40%">URL</th>
                        <th data-column-id="ping" data-formatter="ping" data-sortable="false"> Ping</th>
                        <th data-column-id="cpu" data-formatter="cpu" data-sortable="false"> CPU</th>
                        <th data-column-id="mem" data-formatter="mem" data-sortable="false"> Memory</th>
                    </tr>
                </thead>
            </table>
            <script>
                window.myLine = new Array();
                window.myCPUPie = new Array();
                window.myMEMPie = new Array();
                function addData(id, value) {
                    window.myLine[id].data.labels.push("");
                    window.myLine[id].data.datasets.forEach(function (dataset) {
                        dataset.data.push(value);
                    });
                    window.myLine[id].update();
                }
                function removeData(id) {
                    window.myLine[id].data.labels.shift();
                    window.myLine[id].data.datasets.forEach(function (dataset) {
                        dataset.data.shift();
                    });
                    window.myLine[id].update();
                }

                function getPing(id) {
                    $.ajax({
                        url: 'ping/' + id,
                        success: function (response) {
                            removeData(id);
                            addData(id, response);
                            $('#ping' + id).text("Ping: " + response + " ms");
                            setTimeout(function () {
                                getPing(id);
                            }, 1000);
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
                                window.myCPUPie[id].data.datasets[0].data = [100 - response.cpu.percent, response.cpu.percent];
                                window.myCPUPie[id].options.title.text = response.cpu.title;
                                window.myCPUPie[id].update();

                                window.myMEMPie[id].data.datasets[0].data = [100 - response.memory.percent, response.memory.percent];
                                window.myMEMPie[id].options.title.text = response.memory.title;
                                window.myMEMPie[id].update();

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
                            }, 1000);
                        },
                        error: function () {
                            window.myCPUPie[id].data.datasets[0].data = [0, 0];
                            window.myCPUPie[id].options.title.text = "Offline";
                            window.myCPUPie[id].update();

                            window.myMEMPie[id].data.datasets[0].data = [0, 0];
                            window.myMEMPie[id].options.title.text = "Offline";
                            window.myMEMPie[id].update();
                            goOffline(id);
                            setTimeout(function () {
                                getEncoder(id, siteURL);
                            }, 2000);
                        }
                        
                    });
                }

                $(document).ready(function () {

                    var gridEncoder = $("#gridEncoder").bootgrid({
                        ajax: true,
                        url: "encoders.json",
                        formatters: {
                            "siteURL": function (column, row) {
                                return "<a class='btn btn-primary' href='" + row.siteURL + "?webSiteRootURL=<?php echo urlencode($streamer->getSiteURL()); ?>&user=<?php echo $streamer->getUser(); ?>&pass=<?php echo $streamer->getPass(); ?>'>" + row.siteURL + "</a><br>" + row.description;
                            },
                            "status": function (column, row) {
                                return queuesize + maxfilesize;
                            },
                            "cpu": function (column, row) {
                                var cpu = '<canvas id="cpu' + row.id + '" rowId="' + row.id + '" siteURL="' + row.siteURL + '" class="pie" height="150"></canvas>';
                                return cpu;
                            },
                            "mem": function (column, row) {
                                var mem = '<canvas id="mem' + row.id + '" rowId="' + row.id + '" siteURL="' + row.siteURL + '" class="pie" height="150"></canvas>';
                                return mem;
                            },
                            "ping": function (column, row) {
                                var canvas = '<canvas id="canvas' + row.id + '" rowId="' + row.id + '" siteURL="' + row.siteURL + '" class="ping" height="100"></canvas>';
                                var labels = '<br><span id="label' + row.id + '" class="label label-danger">Offline</span><span id="ping' + row.id + '" class="label label-default">Searching Ping ...</span>';
                                var queuesize = '<br><span id="queuesize' + row.id + '" class="label label-default">Queue Size 0</span>';
                                var maxfilesize = '<br><span id="maxfilesize' + row.id + '" class="label label-default">Max File Size 0Mb</span>';
                                return canvas + labels + queuesize + maxfilesize;
                            }
                        }
                    }).on("loaded.rs.jquery.bootgrid", function () {
                        $('.ping').each(function () {
                            var id = $(this).attr('rowId');
                            window.myLine[id] = new Chart(document.getElementById("canvas" + id).getContext("2d"), {
                                animation: false,
                                type: 'line',
                                data: {
                                    labels: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0,0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                                    datasets: [{
                                            backgroundColor: 'rgba(255, 0, 0, 0.3)',
                                            borderColor: 'rgba(255, 0, 0, 0.5)',
                                            data: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0,0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
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

                            window.myCPUPie[id] = new Chart(document.getElementById("cpu" + id).getContext("2d"), {
                                type: 'pie',
                                data: {
                                    datasets: [{
                                            data: [
                                                0,
                                                0,
                                            ],
                                            backgroundColor: [
                                                'rgba(0, 255, 0, 0.3)',
                                                'rgba(255, 0, 0, 0.3)'
                                            ],
                                            label: 'CPU'
                                        }],
                                    labels: [
                                        "Free",
                                        "Used"
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    legend: {
                                        display: false
                                    },
                                    title: {
                                        display: true,
                                        text: 'Loading...'
                                    }
                                }
                            });

                            window.myMEMPie[id] = new Chart(document.getElementById("mem" + id).getContext("2d"), {
                                type: 'pie',
                                data: {
                                    datasets: [{
                                            data: [
                                                0,
                                                0,
                                            ],
                                            backgroundColor: [
                                                'rgba(0, 255, 0, 0.3)',
                                                'rgba(255, 0, 0, 0.3)'
                                            ],
                                            label: 'Memory'
                                        }],
                                    labels: [
                                        "Free",
                                        "Used"
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    legend: {
                                        display: false
                                    },
                                    title: {
                                        display: true,
                                        text: 'Loading...'
                                    }
                                }
                            });

                            getPing(id);
                            getEncoder(id, $(this).attr('siteURL'));

                        });
                    });
                });

            </script>
            <?php
        }
        ?>
        <div style="width:75%;">
            <canvas id="canvas"></canvas>
        </div>
        velocidade do ping ida<br>
        ping volta (pra saber se pode nos ver)
        Memoria servidor<br>
        CPU<br>
        fila<br>
        resolucao dos videos<br>
        avaliaçao<br>
        Max file size
    </body>
</html>
