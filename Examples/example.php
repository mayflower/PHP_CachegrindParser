<?php

test1();
test4();

function test1() {
  test2();
}

function test2() {
  test3();
  test3();
  test3();
  test3();
}

function test3() {
}

function test4() {
}
// exit;
