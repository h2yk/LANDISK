build:
	mkdir build
	make binary
	make update-files
	cd build && \
	tar acvf ../update_HLS-T.tgz .
	rm -r build
	@echo ALL DONE

binary:
	cp source/for_HLS-T_series \
	source/landisk-update.sh \
	build
	cat build/for_HLS-T_series
	cat build/landisk-update.sh
	@echo "make binary/update files:done"

update-files:
	cd source/update-files.tar.gz && \
	tar acvf ../../build/update-files.tar.gz .
	@echo "make update-files:done"