.PHONY : clean cellnet testbed testpath debug install objects 

VERSION = 0.5a

DEBUG_FLAGS = -D_DEBUG_VERBOSE_ #-D_DEBUG_FLOW_ -D_DEBUG_POINTERS_ #-D_DEBUG_LOCKS_ -D_DEBUG_FAILURES_ 

MKDIR   =   /bin/mkdir
CC	  =   /usr/bin/gcc
CFLAGS  =   -O2 -Wall -Wno-format-extra-args -I../include -I./headers -std=gnu99 -mmmx -msse -msse2 -msse3 $(DEBUG_FLAGS) -D_SHOW_WARNINGS_ -D_SHOW_ERRORS_
DEPEND	=	-lm -lgd

BASEDIR =   /usr/local/ezfw
BUILD   =   ../../build
OBJDIR	=	.objs

OBJS	=   $(addprefix $(OBJDIR)/, $(SRCS:.c=.o))
MAINOBJS=   $(addprefix $(OBJDIR)/, $(MAINSRCS:.c=.o))

all	 : $(OBJS) cellnet testbed #testpath

$(OBJDIR)/%.o : %.c
	$(MKDIR) -p $(BUILD)
	$(MKDIR) -p $(OBJDIR)
	$(CC) $(CFLAGS) -c $< -o $@
	
debug   :   DEBUG_FLAGS += -D_DEBUGGING_
debug   :   all


