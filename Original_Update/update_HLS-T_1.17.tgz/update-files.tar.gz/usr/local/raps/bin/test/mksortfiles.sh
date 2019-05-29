#!/bin/sh -x
TARGET="$1"
[ "${TARGET}" = "" ] && exit 1

get_filename() {
	local SEED="$1"
	echo -n "${SEED}" | md5sum | cut -d ' ' -f 1
}

INDEX=0
while [ ${INDEX} -lt 3000 ]; do
	get_filename ${INDEX} \
		| xargs -i{} dd if=/dev/zero of="${TARGET}/{}" bs=1 count=${INDEX}
	INDEX=$((INDEX+1))
done

touch "${TARGET}/`get_filename 3`"

exit 0
