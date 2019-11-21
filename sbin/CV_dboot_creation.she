# Colored Text
PASSC="\033[34;01m"
FAILC="\033[31;01m"
RESETC="\033[37;00m"
NAMEC="\033[36;01m"
BOLDC="\033[01m"
WARNC="\033[33;01m"


# find the FLASHIDE

if [ -b /dev/hdc ]; then
    FLASHIDE=/dev/hdc
elif [ -b /dev/hdd ]; then
    FLASHIDE=/dev/hdd
else
    # get all disks, kick out any parts or usb devices
    DISKS=`ls /dev/disk/by-path/ | grep -v part | grep -v usb`

    # determine which one is the harddrive, intentially ignore number
    HD=`df | grep ' /$' | grep -E -o "^[a-zA-Z/]+"`

    # panic if more than 2 devices found
    WC=`ls /dev/disk/by-path/ | grep -v part | grep -v usb | wc -l`
    if [ $WC != 2 ]; then
        printf "${WARNC}Too many drives found, found $WC but expected 2.${RESETC}\n"
        exit 0
    fi

    # now find it
    for i in $DISKS;do
        DEVICE=$(stat /dev/disk/by-path/$i | grep File | grep -o hd[abcd] || stat /dev/disk/by-path/$i | grep File | grep -o sd[abcd])
        if [ "/dev/$DEVICE" = $HD ]; then
            printf "Disk found - ${i} - ${DEVICE} is the harddrive\n"
        else
            printf "Disk found - ${i} - ${DEVICE} is the flash\n"
            FLASHIDE=/dev/$DEVICE        
        fi
    done;
fi


# last check if FLASHIDE was determined
if [ $FLASHIDE ]; then
    printf "FLASHIDE determined as ${FLASHIDE}\n"
else
    printf "${WARNC}No flash device detected -- no filesystems to create.${RESETC}\n"
    exit 0
fi


printf "${NAMEC}Flash device is ${FLASHIDE}${RESETC}\n"

PART1=1
PART2=2
LABEL1="CV_dboot"
LABEL2="CV_flashide"

printf "${NAMEC}Bringing down services...${RESETC}\n"
/usr/bin/svc -d /etc/service/copier;
sleep 10;

printf "${NAMEC}Unmounting ${FLASHIDE} partitions...${RESETC}\n"
/bin/umount $FLASHIDE$PART1
/bin/umount $FLASHIDE$PART2


printf "${NAMEC}Partitioning flashide ${FLASHIDE}... ${RESETC}\n"
sfdisk -f -uS ${FLASHIDE} <<EOF
63,161217,6,*
161280,,83,
EOF

printf "${NAMEC}Making ${LABEL1}... ${RESETC}\n"
mkfs.vfat -n ${LABEL1} ${FLASHIDE}${PART1} || printf "${FAILC}Making ${LABEL1} failed ($?)${RESETC}\n"

printf "${NAMEC}Making ${LABEL2}... ${RESETC}\n"
mkfs.ext3 -q -L ${LABEL2} -O sparse_super -T largefile4 $FLASHIDE$PART2 || printf "${FAILC}Making ${LABEL2} failed ($?)${RESETC}\n"

#  Bug 332
udevtrigger     # update the /dev tree after modifiying the flash module partitions & labels 

printf "${NAMEC}Copying Linux Kernel and InitRD to ${FLASHIDE}${PART1}...${RESETC}\n"

mkdir /mnt/flashide-part1
mount -L ${LABEL1} /mnt/flashide-part1 || printf "${FAILC}Mounting ${LABEL1} failed ($?)${RESET}\n"
cp /vmlinuz /mnt/flashide-part1/linux
cp /initrd.img /mnt/flashide-part1/initrd.img
sync

echo "DEFAULT linux initrd=initrd.img root=LABEL=CV_root apci=off panic=30 panic_on_oops=30" > /mnt/flashide-part1/syslinux.cfg
sync
umount /mnt/flashide-part1

printf "${NAMEC}Setting Master Boot Record for flashide ${FLASHIDE}... ${RESETC}\n"
dd if=/usr/lib/syslinux/mbr.bin of=${FLASHIDE}

printf "${NAMEC}Installing SYSLINUX bootloader on ${FLASHIDE}${PART1}... ${RESETC}\n"
syslinux ${FLASHIDE}${PART1}

rm -fR /mnt/flashide-part1


printf "${NAMEC}Mounting the partition ${LABEL2}... ${RESETC}\n"
/bin/mount -L ${LABEL2} || printf "${FAILC}Mounting ${LABEL2} failed ($?)${RESET}\n"

printf "${NAMEC}Bringing up services...${RESET}\n"
/usr/bin/svc -u /etc/service/copier;

printf "${NAMEC}Done setting up CV_dboot ${RESETC}\n"


if ps p `cat /var/run/mysqld/mysqld.pid` | grep 'mysql'; then
    if [ -x /usr/local/ezfw/sbin/validate_videos.php ]; then 
        printf "${NAMEC}Validating videos with '/usr/local/ezfw/sbin/validate_videos.php...${RESETC}\n"
        /usr/local/ezfw/sbin/validate_videos.php | tee -a /usr/local/ezfw/logs/validate_videos.log 2>&1
        printf "${NAMEC}...Done${RESETC}\n";
    else
        printf "${FAILC}Error: Validate videos script either does not exist or is not executable...${RESETC}\n";
    fi
else
    printf "${FAILC}Error: Could not call to validate videos: mysql server is not running....${RESETC}\n";
fi


