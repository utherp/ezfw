.PHONY = all makever clean clean-all clean_% build-all build_% link unlink debug

export SRCDIR = $(CURDIR)
MAKEFILENAME  = $(word $(words $(MAKEFILE_LIST)), $(MAKEFILE_LIST)).config
export CONFMAKE := $(SRCDIR)/$(MAKEFILENAME)

ifdef CV_BUILD_VERSION
  ifeq ("latest",$(CV_BUILD_VERSION))
  	# remove build version, the target for .build_version will set it to the newest
    NOTHING	:=  $(shell rm .build_version buildver)
  else
    # if the version doesn't exist, remove build_version to be set to the newest
    NOTHING :=  $(shell [ ! -d "cellnet-$(CV_BUILD_VERSION)" ] && echo "NEXISTS" || echo "$(CV_BUILD_VERSION)" > .build_version && rm -f buildver || true )
  endif
endif

ALL_VERS	:=	$(wildcard cellnet-*)
LATEST_VER	:=  $(word $(words $(ALL_VERS)), $(ALL_VERS))

all:	makever

makever: buildver
	$(MAKE) -C buildver

buildver: .build_version
	ln -sf cellnet-`cat .build_version` buildver

.build_version:
	[ -e .build_version ] || echo "$(subst cellnet-,,$(LATEST_VER))" > .build_version

clean:
	[ -d buildver ] && $(MAKE) -C buildver clean || true

link: makever
	$(MAKE) -C buildver link

unlink: buildver
	$(MAKE) -C buildver unlink

debug: buildver clean
	$(MAKE) -C buildver debug

build-all:  $(addprefix build_,$(ALL_VERS))
build_%: $(subst build_,,$@) | force
	$(MAKE) -C $* build

clean-all:	$(addprefix clean_,$(ALL_VERS))
clean_%: $(subst clean_,,$@) | force
	echo "all vers: '$(ALL_VERS)'"
	echo "target: '$@', $*"
	[ -d "$*" ] && $(MAKE) -C $* clean || true

force: ; 

