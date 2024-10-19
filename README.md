# Arrigoo CDP Block control

Control display of your Gutenberg Blocks based on segments from the Arrigoo CDP.

The plugin will add a control to all blocks that allows you to select which segments to display it to. If nothing is selected, all will be displayed.

In frontend, a script is loaded that communicates with the CDP API to exchange data about the user and show the correct blocks for the user.

NB: All the blocks are being rendered, but will remain hidden until the frontend script determine whether to display or remove them.

Requires an autoloader https://deliciousbrains.com/storing-wordpress-in-git/

Read more about the Arrigoo CDP at https://arrigoo.io
