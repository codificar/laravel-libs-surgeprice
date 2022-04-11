#!/usr/bin/python

import sys, os, getopt
from joblib import load

from sklearn.neighbors import LocalOutlierFactor
from sklearn import cluster

def main(argv):
    # Validate input arguments
    try:
        opts, args = getopt.getopt(argv,"ha:o:p:")
    except:
        print('Usage:')
        print('predict-single.py -a <latitude> -o <longitude> -p <path>')
        sys.exit(2)
    for opt, arg in opts:
        if opt == '-h':
            print('predict-single.py -a <latitude> -o <longitude> -p <path>')
            sys.exit()
        elif opt in ["-a"]:
            lat = float(arg)
        elif opt in ["-o"]:
            lng = float(arg)
        elif opt in ["-p"]:
            path = arg
    
    try:
        lat
        lng
        path
    except:
        print('Usage:')
        print('predict-single.py -a <latitude> -o <longitude> -p <path>')
        sys.exit(2)

    data = [[lat, lng]]
    # Iterate over each state, looking for the location's surge area, if any
    for state in next(os.walk(path))[1]:
        currentpath = path + '/' + state
        try:
            # Load Local Outlier Factor model for novelty/anomally detection (region boundaries)
            lof = load(currentpath+'/lof-model.joblib')
            # Load K-means model for clusters (surge areas) inference
            kmeans = load(currentpath+'/kmeans-model.joblib')
        # no ML models for region, go to next one
        except FileNotFoundError:
            continue

        # Location belongs to region
        if(lof.predict(data)[0] > 0):
            # Print region and area id
            print(state,kmeans.predict(data)[0])
            return
    print('- -')

if __name__ == "__main__":
    main(sys.argv[1:])