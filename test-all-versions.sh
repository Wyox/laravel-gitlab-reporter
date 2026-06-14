#!/bin/bash

set -e

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

VERSIONS=("laravel11-php82" "laravel12-php84" "laravel13-php84")
FAILED=()
PASSED=()

echo -e "${YELLOW}Building and testing all Laravel versions...${NC}\n"

for version in "${VERSIONS[@]}"; do
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}Testing: ${version}${NC}"
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    if docker compose build --no-cache "$version" && docker compose run --rm "$version"; then
        echo -e "${GREEN}✓ ${version} passed${NC}\n"
        PASSED+=("$version")
    else
        echo -e "${RED}✗ ${version} failed${NC}\n"
        FAILED+=("$version")
    fi
done

echo -e "\n${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}Summary${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

if [ ${#PASSED[@]} -gt 0 ]; then
    echo -e "${GREEN}Passed (${#PASSED[@]}):${NC}"
    for v in "${PASSED[@]}"; do
        echo -e "  ${GREEN}✓ $v${NC}"
    done
fi

if [ ${#FAILED[@]} -gt 0 ]; then
    echo -e "${RED}Failed (${#FAILED[@]}):${NC}"
    for v in "${FAILED[@]}"; do
        echo -e "  ${RED}✗ $v${NC}"
    done
    exit 1
fi

echo -e "\n${GREEN}All tests passed!${NC}"
