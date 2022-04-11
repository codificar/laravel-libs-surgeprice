#!/usr/bin/python

import sys, os, getopt
from joblib import load

import pandas as pd
import numpy as np
from sklearn.neighbors import LocalOutlierFactor
from sklearn import cluster

# Super sample points around a location using normal distribution
def SuperSamplePoint(point, weight, ef, qtd):
    # generate normal distribution
    sample = np.random.normal(scale=0.2,size=(int(weight*100),2))
    # weight sample coordinates based on expand factor and total clusters
    sample *= ef/qtd
    # relocate points around cluster centroid
    sample[:, 0] = sample[:, 0] + point.lat
    sample[:, 1] = sample[:, 1] + point.lng
    return pd.DataFrame(data=sample, columns=["lat", "lng"])

# Create sample points around each area centroid to form clusters
def superSampleCentroids(centroids, weights, ef, lof, kmeans):
    all_clusters = pd.DataFrame(columns=["lat", "lng", "area_index"])
    for i in centroids.index:
        c = centroids.loc[i]
        # super sample points for area
        cluster = SuperSamplePoint(c, weights[i], ef, len(centroids))
        # Exclude outliers
        pred = lof.predict(cluster)
        inlier = cluster[pred > 0]
        # Exclude non area points
        pred = kmeans.predict(inlier.values)
        cluster = inlier[pred == i]
        # Append area index
        cluster['area_index'] = i

        all_clusters = pd.concat([all_clusters, cluster], ignore_index=True, sort=False)
    return all_clusters

def main(argv):
    # Disable chained assignment (false positive) warning
    pd.options.mode.chained_assignment = None
    # Validate input arguments
    try:
        opts, args = getopt.getopt(argv,"hs:e:m:p:")
    except:
        print('Usage:')
        print('heatmap.py -s <region state> -e <expand factor> -m <multipliers> -p <path>')
        sys.exit(2)
    for opt, arg in opts:
        if opt == '-h':
            print('heatmap.py -s <region state> -e <expand factor> -m <multipliers> -p <path>')
            sys.exit()
        elif opt in ["-s"]:
            state = arg
        elif opt in ["-e"]:
            expandfactor = float(arg)
        elif opt in ["-m"]:
            multipliers = eval(arg)
        elif opt in ["-p"]:
            path = arg
    
    try:
        state
        expandfactor
        multipliers
        path
    except:
        print('Usage:')
        print('heatmap.py -s <region state> -e <expand factor> -m <multipliers> -p <path>')
        sys.exit(2)

    # go to state path to retrieve models and surge areas centroids
    path += '/' + state
    try:
        # Load Local Outlier Factor model for novelty/anomally detection (region boundaries)
        lof = load(path+'/lof-model.joblib')
        # Load K-means model for clusters (surge areas) inference
        kmeans = load(path+'/kmeans-model.joblib')
        centroids = pd.read_csv(path+'/centroids.csv', names=['lat', 'lng'] , header=None)
    # no ML models for region, go to next one
    except FileNotFoundError:
        print('No models found!')
        return

    # Create heatmap points around area centroids
    heatmap = superSampleCentroids(centroids, multipliers, expandfactor, lof, kmeans)

    # Print heatmap points
    for p in heatmap.values:
        print(p[0],p[1],int(p[2]))

if __name__ == "__main__":
    main(sys.argv[1:])