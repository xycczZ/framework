#!/usr/bin/env bash

set -e
set -x

BASE_PATH=$(cd `dirname $0`; cd ../src; pwd)
REPOS=$@

CURRENT_BRANCH='master'

function split()
{
    SHA1=`./bin/splitsh-lite-linux --prefix=$1`
    git push $2 "$SHA1:refs/heads/$CURRENT_BRANCH" -f
}

function remote()
{
    git remote add $1 $2 || true
}

git pull origin $CURRENT_BRANCH

if [[ $# -eq 0 ]]; then
  REPOS=$(ls $BASE_PATH)
fi

for dir_name in $REPOS
do
  repo=`echo $dir_name | tr A-Z a-z`
  remote $repo git@git.invoker.love:framework-org/${repo}.git
  split "src/$dir_name" $repo
done