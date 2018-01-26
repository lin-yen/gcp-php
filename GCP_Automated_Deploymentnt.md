# gcp 自動部署

## you need
### some example project
題目：實作 go 與 php 在機器內部互打 api，取得對方的 hello world。

project|clone with HTTPS
:-:|:-
gcp-php|[https://github.com/lin-yen/gcp-php.git](https://github.com/lin-yen/gcp-php.git)
gcp-nginx|[https://github.com/lin-yen/gcp-nginx.git](https://github.com/lin-yen/gcp-nginx.git)
gcp-go|[https://github.com/lin-yen/gcp-go.git](https://github.com/lin-yen/gcp-go.git)

相關設定|值
:-:|:-
cluster|cluster-test
zone|asia-east1-c

### gcp
- 申請自己的 gcp (官方贈送 300 美元，可使用 1 年，到期或用光會暫停 gcp 服務，除非使用者同意，否則不會自動扣款。)
- 建立一個 project
- 安裝 gcp SDK
  - [https://cloud.google.com/sdk/downloads](https://cloud.google.com/sdk/downloads)
  - 下載並解壓縮至根目錄
  - install: `./google-cloud-sdk/install.sh`
  - PATH: `export PATH=$PATH:/Users/apple/google-cloud-sdk/bin`
  - gcloud init
- 安裝 kubernetes
  - [https://cloud.google.com/kubernetes-engine/docs/quickstart](https://cloud.google.com/kubernetes-engine/docs/quickstart)
  - `gcloud components install kubectl`

#### create cluster
steps|use command-line
:-|:-
set gcloud zone|`gcloud config set compute/zone asia-east1-c`
creat a cluster with zone|`gcloud container clusters create cluster-test --zone asia-east1-c`
get cluster credentials|`gcloud container clusters get-credentials cluster-test`

基礎語法分享:

do some thing|synopsis
:-|:-
Check config list|gcloud config list
Check projects|gcloud projects list
Setting a default project|gcloud config set project [PROJECT-ID]
See zones valid choices|gcloud compute zones list
Setting a default compute zone|gcloud config set compute/zone [ZONE]
Creating a Kubernetes Engine cluster|gcloud container clusters create [CLUSTER-NAME]
Check clusters|gcloud container clusters list
Setting clusters|gcloud container clusters get-credentials [CLUSTER-NAME]
查看 Nodes|kubectl get nodes
用設定檔建立 deployment|kubectl create -f [FILE NAME]
用設定檔移除 deployment|kubectl delete -f [FILE NAME]
查看 deployment|kubectl get deployment
查看 pods|kubectl get pod
移除 pod (如果有 deployment，pod 被移除後會自動重啟)|kubectl delete pod [POD NAME]
用設定檔建立 services|kubectl create -f [FILE NAME]
用設定檔移除 services|kubectl delete -f [FILE NAME]
查看 services|kubectl get services
移除 services|kubectl delete services [SERVICES NAME]
進機器|kubectl exec -it [POD NAME] bash


#### create gcp repo
建立三個 repo，並將三個範例檔案內容推到相對應的 repo 內。steps:
- 開啟 gcp 主控台 > Source Repositories > Repositories > CREAT REPOSITORY
- gcloud 驗證: `git config credential.helper gcloud.sh`
- check `git config -l` and you will see 「credential.helper=gcloud.sh」in your conf
- 本機專案 add remote gcp repo
- push

---
## Automating Builds using Build Triggers
[https://cloud.google.com/container-builder/docs/running-builds/automate-builds](https://cloud.google.com/container-builder/docs/running-builds/automate-builds)  
### cloudbuild.yaml
請移除範例檔的 cloudbuild.yaml 內第二個 steps (kubectl set image 的部分)，以 go 為例：
```
steps:
- name: 'gcr.io/cloud-builders/docker'
  args: ['build', '-f', 'docker/Dockerfile', '-t', 'gcr.io/$PROJECT_ID/php:$SHORT_SHA', '.']

images:
- 'gcr.io/$PROJECT_ID/php:$SHORT_SHA'
```

### steps
- Add trigger
  - 開啟 gcp 主控台 > Container Registry > Build triggers > Add trigger
  - Cloud Source Repository
  - Trigger Type：branch master
  - Build Configuration：cloudbuild.yaml
- check build
  - 開啟 gcp 主控台 > Container Registry > Build triggers 可以查看剛剛建立的事件
    - 將建立的 trigger 點 Run trigger > master
  - 開啟 gcp 主控台 > Container Registry > Build History
  - 觀察 images build 的狀況

錯誤處理:

error code|you need to do
:-|:-
404 (No cluster named 'cluster-test' in [project])|請移除範例檔的 cloudbuild.yaml 內第二個 steps (kubectl set image 的部分)
403|請至 IAM 開啟「xxx@cloudbuild.gserviceaccount.com」的「Cloud Container Builder」權限。
其他|請拋出錯誤訊息讓大家一起研究，紀錄下錯誤處理方式。

### 本機端測試
使用 docker 在本機端拉 gcp 的 images 下來做測試。  
請先安裝 docker。  
docker 需做 gcp 驗證: [https://cloud.google.com/container-registry/docs/advanced-authentication](https://cloud.google.com/container-registry/docs/advanced-authentication)
```
gcloud components install docker-credential-gcr
docker-credential-gcr configure-docker
```
使用 docker-compose 做整合測試。  
- 在 Build History 內 查看 images Artifacts
- 寫一支 docker-compose.yml (有興趣的請去學一下 docker)
```
# cloud images test
version: '3'
services:
    nginx-service:
        container_name: nginx-service
        image: [your nginx image artifacts]
        ports:
            - "80:80"
        restart: always
        depends_on:
            - php-service
    php-service:
        container_name: php-service
        image: [your php image artifacts]
        restart: always

    go-service:
        container_name: go-service
        image: [your go image artifacts]
        ports:
            - "4000:4000"
        restart: always
```
- 啟動: `docker-compose up -d`
- check container: `docker container ls`
- 加 host `127.0.0.1 nginx-service`
- php 打 go
  - http://nginx-service/getHelloGo.php
  - you will get「Hello world! by go.」
- go 打 php
  - http://localhost:4000/getHelloPHP
  - you will get「Hello world! by php.」
- close: `docker-compose down`

---
## Kubernetes Deployments
[https://kubernetes.io/docs/concepts/workloads/controllers/deployment/](https://kubernetes.io/docs/concepts/workloads/controllers/deployment/)

### deployment.yaml
範例檔的 deployment.yaml，以 go 為例：
```
apiVersion: extensions/v1beta1
kind: Deployment
metadata:
  name: go
  labels:
    app: go
spec:
  replicas: 1
  selector:
    matchLabels:
      app: go
  template:
    metadata:
      labels:
        app: go
    spec:
      containers:
      - name: go
        image: go
        imagePullPolicy: Always
        ports:
        - containerPort: 4000
```

### 建立 deployment
- create: `kubectl create -f deployment.yaml`
- check: `kubectl get deployments`
- see the rollout status: `kubectl rollout status deployment/[NAME]` <- 我還不懂是在看啥
- see the ReplicaSet (rs) created by the deployment: `kubectl get rc` <- 我還不懂是在看啥

基本上建立後就會常駐在背景，直到你關掉為止。  

錯誤處理:

error code|you need to do
:-|:-
403|請至 IAM 開啟「xxx@cloudbuild.gserviceaccount.com」的「Kubernets Engine」權限。
其他|請拋出錯誤訊息讓大家一起研究，紀錄下錯誤處理方式。

### Updating a Deployment
官方提供的更新語法為: `kubectl set image deployment/go-deployment go=go:1.9.1`  
為了在建立好新的 images 時會去做自動部署的動作，我們將此語法寫入「cloudbuild.yaml」。以 go 為例:
```
steps:
- name: 'gcr.io/cloud-builders/docker'
  args: ['build', '-f', 'docker/Dockerfile', '-t', 'gcr.io/$PROJECT_ID/go:$SHORT_SHA', '.']
  id: 'build'
- name: 'gcr.io/cloud-builders/kubectl'
  args: ['set', 'image', "deployment/go", "go=gcr.io/$PROJECT_ID/go:$SHORT_SHA"]
  env:
  - 'CLOUDSDK_COMPUTE_ZONE=asia-east1-c'
  - 'CLOUDSDK_CONTAINER_CLUSTER=cluster-test'
  wait_for: ['build']
images:
- 'gcr.io/$PROJECT_ID/go:$SHORT_SHA'
```
- 使用 kubectl 去做 set image 的動作
- CLOUDSDK_CONTAINER_CLUSTER 為 cluster name
- CLOUDSDK_COMPUTE_ZONE 為 cluster zone

---
## Kubernetes Services
[https://kubernetes.io/docs/concepts/services-networking/service/](https://kubernetes.io/docs/concepts/services-networking/service/)  

### service.yaml
範例檔的 service.yaml，以 go 為例：
```
kind: Service
apiVersion: v1
metadata:
  name: go-service
spec:
  selector:
    app: go
  ports:
  - protocol: TCP
    port: 4000
    targetPort: 4000
  type: LoadBalancer
```
type: LoadBalancer <- 開啟服務給外部使用

### 建立 services
- create: `kubectl create -f service.yaml`
- check: `kubectl get services`

基本上建立後就會常駐在背景，直到你關掉為止。

### check
- 加 host `[nginx-service 的外部 IP] nginx-service`
- php 打 go
  - http://nginx-service/getHelloGo.php
  - you will get「Hello world! by go.」
- go 打 php
  - http://[go-service 的外部 IP]:4000/getHelloPHP
  - you will get「Hello world! by php.」
