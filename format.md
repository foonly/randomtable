# RandomTable Format Guide

This parser uses a format developed by Dr. J.M. "Thijs" Krijger <matthijs.krijger@gmail.com>, documentation is written by Niklas Schönberg <niko@uplink.fi>.

The format uses a few concepts that at it's simplest is very easy to use, but still allows for a lot of complexity when needed.

## Formatting Basics
First a few guidelines about the syntax. 

### Spaces
Spaces are very important, and care should be taken to not insert spaces where there should be none. The number of successive spaces does not matter, and are often, but not always removed in the output.

### Lines
Where you put the newline character is also important. Conditional and weighted rows are always ended when the row ends. Empty lines are ignored. You can however have a weighted line with nothing after the weight.

### Comments
Comments can be entered using the semicolon character (;). Anything after the semicolon is removed before parsing.

## Tables
Tables are the backbone of the format. A table is always executed in order from first row to last row. All commands are executed in order, with the exception of [2D6] dice notation is always evaluated first, so you can use it in table and variable names.
A table definition always start with #tablename.

