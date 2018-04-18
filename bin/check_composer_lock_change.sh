#!/bin/sh

if [ "post-merge" = "$1" ]; then
    from="ORIG_HEAD"
    to="HEAD"
elif [ "post-checkout" = "$1" ]; then
    from="$2"
    to="$3"
else
    exit 0
fi

git diff-tree -r --name-only --no-commit-id "$from" "$to" | grep composer.lock > /dev/null

if [ "$?" -lt 1 ]
then
    echo
    echo -e "\033[37;1;41m                                                                     \033[0m"
    echo -e "\033[37;1;41m ! ALERT ! \033[0;1;36m composer.lock changed, run \"composer install\" \033[37;1;41m ! ALERT ! \033[0m"
    echo -e "\033[37;1;41m                                                                     \033[0m"
    echo
fi
