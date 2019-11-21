#!/usr/bin/perl -w
#### Definitions ###############################
    my $ON          =   '1';
    my $OFF         =   '2';
    my $TOGGLE      =   '0';
    my $LOCK_FILE   =   '/tmp/toggle_tv.lock';
    my $SWITCHER    =   '/usr/local/sbin/toggle_tv_modulator.sh';
    my $STATUS_FILE =   '/usr/local/ezfw/flags/toggle_tv.status';

    my $TV_CONTROLER =  '/usr/local/ezfw/sbin/change_tv.sh';
    $TV_CONTROLER = 0 unless (-x $TV_CONTROLER);
################################################
    my $DEBUG       = 0;
    my $CONTROL_TV  = 0;

    my $last_stamp  = 0;
    my $last_status = 'OFF';

    my $force_status = @ARGV?$ARGV[0]:$TOGGLE;

    if ($force_status =~ /\D/) {
        if ($force_status =~ /^on$/i) {
            $force_status = $ON;
        } elsif ($force_status =~ /^off$/i) {
            $force_status = $OFF;
        } else {
            $force_status = $TOGGLE;
        }
    } elsif ($force_status != $ON && $force_status != $OFF && $force_status != $TOGGLE) {
        $force_status = $TOGGLE;
    }
#### MAIN ######################################

    unless ( -e $STATUS_FILE ) {
        set_status($OFF);
        exit if ($force_status == $OFF);
    }

    if (lock_exists()) {
        my $test = 10;
        while (process_running()) {
            if ($DEBUG) { print "Locked!\n"; }
            last unless $test--;
            sleep(1);
        }
        exit unless $test;
    }
    

    create_lock();
    read_status();

#   $current_stamp = `date +%s`;
#   $current_stamp =~ s/\n//;
#   $current_stamp = int($current_stamp);

    $current_stamp = time();

    if ($force_status != $TOGGLE) {
        # Back when the node had an IR dongle to control the TV, there was a reason
        # for this check -- but we can't remember what it was! As of now, it looks
        # like this is safe to remove and this allows /etc/init.d/ezfw-node to
        # force the modulator to off.
        #unless ($TV_CONTROLER) {
        #   print STDERR "Warning: Not forcing state when no TV Controller is configured!\n";
        #   remove_lock();
        #   exit(1);
        #}
        if ($force_status == $ON && !tv_on()) {
            switch_tv($ON);
        } elsif ($force_status == $OFF && tv_on()) {
            switch_tv($OFF);
        }

    } else {

        if (($last_stamp > 0) && (($current_stamp - $last_stamp) < 2)) {
            if ($DEBUG) {
                print "Not long enough time has elapsed, not changing!\n";
                print "current = $current_stamp, last = $last_stamp\n";
            }
            remove_lock();
            exit;
        }
        
        
    
        if (tv_on()) {
            switch_tv($OFF);
#           set_status('OFF');
#           send_tv_signals('TV') if $CONTROL_TV;
        } else {
            switch_tv($ON);
#           set_status('ON');
#           send_tv_signals('CAREVIEW') if $CONTROL_TV;
        }
    }

    remove_lock();

    exit;


################################################
#### Functions##################################
################################################
    sub send_tv_signals {
        return unless ($TV_CONTROLER);
        system($TV_CONTROLER . ' ' . $_[0]);
#       system($TV_CONTROLER . " TV") if ($_[0] eq 'TV');
#       system($TV_CONTROLER . " CAREVIEW") if ($_[0] eq 'CAREVIEW');
        return;
    }
###############################################
    sub read_status {
        foreach (`cat $STATUS_FILE`) {
            my @tmp = split(/ /);
            $tmp[1] =~ s/\n//;
            $last_stamp = int($tmp[1]);
            $last_status = $tmp[0];
        }
    }
###############################################
    sub tv_on {
        if ($last_status =~ /ON/) {
            return 1;
        }
        return 0;
    }
################################################
    sub process_running {
        my $proc_pid = `cat $LOCK_FILE`;
        $proc_pid =~ s/\n//g;
        $proc_pid =~ s/[^0-9]//g;
        my $ret = `ps axww |awk '/^$proc_pid/'`;
        $ret =~ s/^([0-9]+).*$/$1/;
        if (length($ret) > 0) { return 1; }
        return 0;
    }
################################################
    sub lock_exists {
        return -e $LOCK_FILE;
    }
################################################
    sub create_lock {
        `touch $LOCK_FILE`;
    }
################################################
    sub remove_lock {
        unlink($LOCK_FILE);
    }
################################################
    sub switch_tv {
        if ($DEBUG) { print "switching tv to $_[0]\n"; }
        send_tv_signals($_[0]);
        set_status($_[0]);

#       if ($_[0] eq $ON) {
#           system("\
#               for i in 0 3; do \
#                   /usr/bin/irsend SEND_ONCE emerson \$i; \
#                   sleep .5; \
#               done; \
#               ");
#           system('/usr/bin/irsend SEND_ONCE emerson 3');
#       } else {
#           system('/usr/bin/irsend SEND_ONCE emerson Recall');
#       }
        system($SWITCHER . ' ' . $_[0]);
    }
################################################
    sub set_status {
        my $status = ($_[0]==$ON)?'ON':'OFF';
        my $stamp = time(); #`date +%s`;
#       $stamp =~ s/\n//;
        if ($DEBUG) { print "setting status to $status\n"; }
        if (! -e $STATUS_FILE) {
            if ($DEBUG) { print "status file does not exist, creating\n"; }
            `touch $STATUS_FILE`;
            `chmod 666 $STATUS_FILE`;
            $stamp = '-1';
        }
        `printf "$status $stamp" > $STATUS_FILE`;
    }
################################################
