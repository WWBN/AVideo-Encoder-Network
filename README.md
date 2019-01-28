# YouPHPTube-Encoder-Network (YEN)
Agregator for YouPHPTube Encoder Servers

YouPHPTube-Encoder-Network (YEN) is a platform designed to streamline your video encoding.

This platform has been implemented so that several encoders can be monitored in real time.

The system works by doing some real-time testing on the encoders servers, with check ping speed, queue encoding length and free memory of each server. 

With this information the (YEN) helps you decide which encoder to use.

The use is the same as using a simple encoder, but this tool will help you choose the best encoder at the time of encoding.

After choosing the encoder you wish to use use the instructions contained here https://github.com/DanielnetoDotCom/YouPHPTube-Encoder/wiki/How-to-submit-videos-to-the-encoder

We have it installed here with 2 encoders https://network.youphptube.com/

# How to install

We have created a similar installer as YouPHPTube installer.
on the first time you tried to access the page you will be directed to the installation page.

# How to add or remove encoders to the network

We have created a tool to help you add or remove encoders. 

this tool is inside the Installation directory and must be executed by command line.

1 - Go to your terminal using SSH and navigate to the directory YouPHPTube-Network/install

2 - type `php encoder.php` and you will see the options

* A - Add new Encoder
* Q - Quit/Exit
* 1 * Remove https://encoder.youphptube.com/
* 2 * Remove https://encoder2.youphptube.com/

Choose the desire option and follow the instructions.
