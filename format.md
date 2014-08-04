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
Tables are the backbone of the format. A table is always executed in order from first row to last row.
All commands are executed in order, with the exception of [2D6] dice notation is always evaluated first, so you can use it in table and variable names.
A table definition always start with #tablename.

### Weighted Rows
Any rows that start with a number are weighted, this means that the number in front of it is the probability that that particular row will be executed. Only weighted row is executed per call to the table.

### Conditional Rows
Conditional rows always start with "$if" and contain "$then", they may or may not contain "$else". The statement between $if and $then is the condition. The condition is two items and a comparison operator. These can be:

* lt - Less than, true if the left item is less than the right.
* lte - Less than or equal, true if the left item is less than or equal to the right.
* gt - Greater than, true if the left item is greater than the right.
* gte - Greater than or equal, true if the left item is greater than or equal to the right.
* eq - Equal to, true if both items are equal.

If the condition is true the row after $then, but before a possible $else is executed. If false then anything after the $else statement is executed.
Weighted rows can be conditional as well, but the $if must be the first text after the number and a space.

### Other Rows
Any row not starting with a number, $if or ";" are always executed, in order from top to bottom. This means that any rows written in the middle of weighted rows will be executed after the weighted rows in front of it if they are chosen, but before the ones after it.

### Row Execution
Execution of a row is done in the following order.

* [nDs] - Any dice notation like [3D6] or [D8] are always executed first.
* $if ... $then statements, because they are always first in the line.
* $table, %variable=1 and %variable are executed in the order they are written, separated by spaces.

### Calling a Table
A table can be called by the $tablename syntax. When called the table is executed in its entirety, and local execution is then resumed.
If called normally one of the weighted rows will be chosen at random, but if called with $tablename(number), the option specified will be chosen.
For example, if the weighted rows are: 3 a, 3 b, 4 c and 2 d, and the table is called with $tablename(7) option c is chosen.

### Sets
If the table is called with the optional $tablename{setname} where setname can be any name you want, the chosen option is added to the set.
When a table is called with a set, an option already in the set can not be chosen again. If no options remain, nothing is chosen. The same set can be used against different tables, but are of course only useful if the tables share some of the values.
The value is added before execution, which means that [1D6] will always match [1D6], and "Hello %hello=+1" does not equal "Hello" even though the output is identical.
You can use as many sets as you wish.