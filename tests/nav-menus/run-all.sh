cd "$( dirname "$0" )"
set -e

for test in switch-*.sh; do
    echo "## $test"
    bash $test;
    echo
done
