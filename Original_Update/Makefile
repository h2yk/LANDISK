build:
	mkdir build
	make binary
	make setting-files
	make update-files
	cd build && \
	tar acvf ../update_HLS-T.tgz .
	rm -r build
	@echo ALL DONE

binary:
	cp \
	update_HLS-T_1.17.tgz/for_HLS-T_series \
	update_HLS-T_1.17.tgz/landisk-update.sh \
	update_HLS-T_1.17.tgz/flash_hlsc.env \
	update_HLS-T_1.17.tgz/flash_hlsc.uboot \
	update_HLS-T_1.17.tgz/rmlist \
	build
	cat build/for_HLS-T_series
	cat build/landisk-update.sh
	cat build/rmlist
	@echo "make binary/update files:done"

setting-files:
	cd update_HLS-T_1.17.tgz/setting-files.tar.gz && \
	tar acvf ../../build/setting-files.tar.gz .
	@echo "make setting-files:done"

update-files:
	cd update_HLS-T_1.17.tgz/update-files.tar.gz && \
	tar acvf ../../build/update-files.tar.gz .
	@echo "make update-files:done"