#! /usr/bin/perl -w
use strict;

if ($#ARGV < 0) {
    print q{
=============================================================
|                                                           |
|               RADIUSdesk radmin wrapper                   |
|        http://sourceforge.net/projects/radiusdesk/        |
|                                                           |
|-----------------------------------------------------------|
|  Syntax - radmin_wrapper.pl stats [auth|acct] <client IP> |
|  Syntax - radmin_wrapper.pl stop freeradius                         |
|  Syntax - radmin_wrapper.pl start freeradius                         |
|                                                           |
=============================================================
};
    exit;
} elsif ($#ARGV < 1) {
    print "You have not provided all of the arguments required\n";
    exit;
}

my $arg1 = $ARGV[0];
my $arg2 = $ARGV[1];
my $arg3 = '';
if(exists($ARGV[2])){
    $arg3 = $ARGV[2];
}

if($arg1 eq 'stats'){
    my $return_val = `radmin -e "stats client $arg2 $arg3"`;
    print($return_val);
}

#___ Start ____
if($arg1 eq 'start'){
    system("/etc/init.d/radiusd start");
}

#___ Stop ____
if($arg1 eq 'stop'){
    system("/etc/init.d/radiusd stop");
}


#___ Uptime ____
if($arg1 eq 'uptime'){
    my $return_val = `radmin -e "show uptime"`;
    print($return_val);
}

#___ Version ____
if($arg1 eq 'version'){
    my $return_val = `radmin -e "show version"`;
    print($return_val);
}

#___ Clients ____
if($arg1 eq 'clients'){
    my $return_val = `radmin -e "show client list"`;
    print($return_val);
}


#____ Modules ____
if($arg1 eq 'modules'){
    my $return_val = `radmin -e "show module list"`;
    print($return_val);
}
