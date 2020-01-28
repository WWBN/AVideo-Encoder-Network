<img src="https://platform.avideo.com/website/assets/151/images/avideo_network.png"/>

# AVideo-Encoder-Network (AEN)
Aggregator for AVideo Platform Encoder Servers

AVideo-Encoder-Network (AEN) is a platform designed to streamline your video encoding.

This platform has been implemented so that several encoders can be monitored in real time.

The system works by doing some real-time testing on the encoders servers, with check ping speed, queue encoding length and free memory of each server.

This article asumes you know about <a href="http://git.encoder.avideo.com/" class="" target="_blank">AVideo Encoder</a>

With this information the (AEN) helps you decide which encoder to use.

The use is the same as using a simple encoder, but this tool will help you choose the best encoder at the time of encoding.

After choosing the encoder you wish to use use the instructions contained here https://github.com/WWBN/AVideo-Encoder/wiki/How-to-submit-videos-to-the-encoder

We have it installed it here with 2 encoders https://network.avideo.com/

# How to install

We have created a similar installer as AVideo installer.
on the first time you tried to access the page you will be directed to the installation page.

# How to add or remove encoders to the network

We have created a tool to help you add or remove encoders.

This tool is inside the installation directory and must be executed by command line.

1 - Go to your terminal using SSH and navigate to the directory AVideo-Network/install

2 - type `php encoders.php` and you will see the options

* A - Add new Encoder
* Q - Quit/Exit
* 1 * Remove https://encoder.avideo.com/
* 2 * Remove https://encoder2.avideo.com/

Choose the desired option and follow the instructions.
