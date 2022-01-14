#!/usr/bin/python

import sys, getopt
from joblib import load

import pandas as pd
from sklearn.neighbors import LocalOutlierFactor
from sklearn import cluster

def main(argv):
    # Validate input arguments
    try:
        opts, args = getopt.getopt(argv,"hi:d:s:p:")
    except:
        print('Usage:')
        print('predict-data.py -i <prediction file> -d <data type> -s <region state> -p <path>')
        sys.exit(2)
    for opt, arg in opts:
        if opt == '-h':
            print('predict-data.py -i <prediction file> -d <data type> -s <region state> -p <path>')
            sys.exit()
        elif opt in ["-i"]:
            predictionfile = arg
        elif opt in ["-d"]:
            datatype = arg
        elif opt in ["-s"]:
            state = arg
        elif opt in ["-p"]:
            path = arg

    try:
        predictionfile
        datatype
        state
        path
    except:
        print('Usage:')
        print('predict-data.py -i <prediction file> -d <data type> -s <region state> -p <path>')
        sys.exit(2)

    # Read train data on base path
    dataset = pd.read_csv(path+'/'+predictionfile, names=['lat', 'lng', 'id'] , header=None)

    # go to state path to retrieve models and save output
    path += '/' + state;

    # Load Local Outlier Factor model for novelty/anomally detection (region boundaries)
    X = dataset[["lat", "lng"]].values
    lof = load(path+'/lof-model.joblib')
    validData = lof.predict(X)

    # Load K-means model for clusters (surge areas) inference
    #kmeans = cluster.MiniBatchKMeans(n_clusters=total_areas)
    #kmeans.fit(X)
    kmeans = load(path+'/kmeans-model.joblib')
    dataClusters = kmeans.predict(X)

    # Merge numpy arrays (cluster index >= 0, outlier = -1)
    # set all outliers as negative values and change to 1-index
    output = validData * (dataClusters+1)
    # clamp all negative values to -1 and get back to 0-index
    output = (output.clip(min=-1)-1).clip(min=-1)

    # Export prediction results
    df = pd.DataFrame(output, columns=['cluster'])
    df['id'] = dataset.id
    # Exclude outliers
    df = df[df.cluster >= 0]
    # cluster, id
    df.to_csv(path+'/'+ datatype +'-output.csv', header = False, index=False)

if __name__ == "__main__":
    main(sys.argv[1:])


