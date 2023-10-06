---
title: Fix sw-colorpicker to convert color value between different mode
issue: NEXT-15592
---
# Administration
* Added a new method `roundingFloat` in `sw-colorpicker` component
* Changed computed property `hslValue` to calculate hsl value more precise
* Changed methods `convertRGBtoHSL`, `convertHEXtoHSL`, `moveSelector` to calculate hsl value more precise 
