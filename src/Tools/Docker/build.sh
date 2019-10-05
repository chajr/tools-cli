#! /bin/bash

source .env

IMAGES=(mysql nginx php71 db_integration_tests adminer elasticsearch elasticsearch_integration_tests smtp mariadb php56 nginx-proxy mongo graylog logstash hhvm hhvm-proxygen blackfire mailhog)
declare -a MAGENTO=(mysql php71 nginx db_integration_tests adminer elasticsearch elasticsearch_integration_tests smtp mongo graylog blackfire)
declare -a HH=(mysql nginx hhvm hhvm-proxygen)

NGINX=(php56-nginx)

show_help() {
    cat << EOF
 Usage: ${0##*/} [-hr] [-i IMAGE_NAME]

 DESCRIPTION

     -h               display this help and exit
     -r               only run containers from docker-compose
     -R               reload image and containers
     -i IMAGE_NAME    allow to build given image
     -g GROUP_NAME    build images from specified group

     -ri IMAGE_NAME   run only specified container
     -Ri IMAGE_NAME   reload only specified container and image
     -rg GROUP_NAME   run containers from specified group
     -Rg GROUP_NAME   reload containers from specified group

 AVAILABLE IMAGES:
EOF
    for i in ${IMAGES[@]}; do
        echo "     "${i}
    done
}

build_image() {
    if [ ! -f ./$1/Dockerfile ]; then
        echo -e -n "\033[0;30m\033[43mDocker file in ./$1 not found.\033[0m\033[0m"
        echo ''; 
        return;
    fi

    docker build -t lizard/$1 ./$1/
}

#start nginx process
run_nginx() {
    docker exec -it $1 "/usr/sbin/nginx"
}

RUN=''
RELOAD=''
IMAGE='all'
GROUP=IMAGES

while getopts hrRg:i: opt; do
    case "$opt" in
    h)
        show_help
        exit 0
        ;;
    R)
        RUN='run';
        RELOAD='reload';
        ;;
    r)
        RUN='run'
        ;;
    i)
        IMAGE=$OPTARG
        ;;
    g)
        GROUP=$OPTARG
        ;;
    *)
        show_help >&2
        exit 1
        ;;
    esac
done

if [ "$IMAGE" == "" ]; then
    IMAGE='all'
fi

echo -e -n "\033[0;37m\033[42mDocker environment builder.\033[0m\033[0m"
echo '';
echo "==================================";
echo '';

export XDEBUG_HOST_IP=`ipconfig getifaddr en0`;

echo -e -n "\033[0;32mInternal IP: \033[0m"
echo ${XDEBUG_HOST_IP}
echo '';

if [ "$RELOAD" == "reload" ]; then
    echo -e -n "\033[0;37m\033[42mReload images.\033[0m\033[0m"
    echo '';

    if [ "$IMAGE" == 'all' ]; then
        for arrVar in ${GROUP}; do
            declare -a 'arr=("${'"$arrVar"'[@]}")'
            for val in "${arr[@]}"; do
                docker restart ${val}
            done
        done
    else
        docker restart "$IMAGE"
    fi
fi

if [ "$RUN" != "run" ]; then
    echo -e -n "\033[0;37m\033[42mBuild images.\033[0m\033[0m"
    echo '';

    if [ "$IMAGE" == 'all' ]; then
        for arrVar in ${GROUP}; do
            declare -a 'arr=("${'"$arrVar"'[@]}")'
            for val in "${arr[@]}"; do
                build_image ${val}
            done
        done
    else
        build_image "$IMAGE"
    fi
fi

echo -e -n "\033[0;37m\033[42mRun Environment.\033[0m\033[0m"
echo '';

if [ "$IMAGE" == 'all' ]; then
    for arrVar in ${GROUP}; do
        declare -a 'arr=("${'"$arrVar"'[@]}")'
        for val in "${arr[@]}"; do
            docker-compose -f docker-compose.yml up -d --build ${val}
        done
    done

    for i in ${NGINX[@]}; do
        run_nginx ${i}
    done
else
    #skip rebuilding image
    docker-compose -f docker-compose.yml up -d --build $IMAGE

    if [[ "${NGINX[@]}" =~ "${IMAGE}" ]]; then
        run_nginx "$IMAGE"
    fi
fi

if [ "$RUN" != "run" -a "$IMAGE" == 'mysql' ]; then
    echo -e -n "\033[0;37m\033[42mSet up database privileges.\033[0m\033[0m"
    echo '';

    sleep 5
    docker exec -it mysql mysql -u root --password=$MYSQL_ROOT_PASS -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' IDENTIFIED BY 'root' WITH GRANT OPTION;";
    docker exec -it mysql mysql -u root --password=$MYSQL_ROOT_PASS -e "FLUSH PRIVILEGES;";
fi

echo -e -n "\033[0;32mEnvironment build
      / )
    .' /
---'  (____
          _)
          __)
         __)
---.______)\033[0m";
echo '';
