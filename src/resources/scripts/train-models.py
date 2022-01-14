#!/usr/bin/python

import sys, getopt
from joblib import dump

import pandas as pd
from sklearn.neighbors import LocalOutlierFactor
from sklearn import cluster

def main(argv):
    # Validate input arguments
    try:
        opts, args = getopt.getopt(argv,"ht:m:n:c:p:")
    except:
        print('Usage:')
        print('train-models.py -t <train file> -m <min area requests> -n <lof neighbors> -c <lof contamination> -p <model files path>')
        sys.exit(2)
    for opt, arg in opts:
        if opt == '-h':
            print('train-models.py -t <train file> -m <min area requests> -n <lof neighbors> -c <lof contamination> -p <model files path>')
            sys.exit()
        elif opt in ["-t"]:
            trainfile = arg
        elif opt in ["-m"]:
            minreqs = int(arg)
        elif opt in ["-n"]:
            n = int(arg)
        elif opt in ["-c"]:
            c = float(arg)
        elif opt in ["-p"]:
            path = arg

    try:
        trainfile
        minreqs
        n
        c
        path
    except:
        print('Usage:')
        print('train-models.py -t <trainfile> -m <min area requests> -n <lof neighbors> -c <lof contamination> -p <model files path>')        
        sys.exit(2)

    # Read train data
    dataset = pd.read_csv(path+'/'+trainfile, names=['lat', 'lng', 'prefix'] , header=None)

    # Prune sparse regions based on min requests parameter
    prefixes = {}   
    for item in dataset.prefix:
        if(item in prefixes):
            prefixes[item] += 1
        else:
            prefixes[item] =1

    top = []
    for item in sorted(prefixes.items(), key=lambda x: x[1], reverse=True):
        if(item[1] < minreqs):
            break
        top.append(item[0])

    ds = dataset[dataset.prefix.isin(top)]
    # Total surge areas
    total_areas = len(ds.prefix.unique())

    # Train Local Outlier Factor model for novelty/anomally detection (region boundaries)
    X = ds[["lat", "lng"]].values
    lof = LocalOutlierFactor(n_neighbors=n, novelty=True, contamination=c)
    lof.fit(X)
    dump(lof, path+'/lof-model.joblib')

    # Learn clusters (surge areas) using K-means 
    kmeans = cluster.MiniBatchKMeans(n_clusters=total_areas)
    kmeans.fit(X)
    dump(kmeans, path+'/kmeans-model.joblib')

    # Export centroids from surge areas
    df = pd.DataFrame(kmeans.cluster_centers_, columns=['lat', 'lng'])
    df.to_csv(path+'/centroids.csv', header = False, index=False)

if __name__ == "__main__":
    main(sys.argv[1:])

